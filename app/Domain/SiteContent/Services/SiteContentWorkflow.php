<?php

namespace App\Domain\SiteContent\Services;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\PublicProjection\Services\PublicSiteContentCache;
use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\Publishing\Exceptions\StalePublicationStateException;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Models\SiteContentPublicationState;
use App\Domain\SiteContent\Models\SiteContentPublicationTransition;
use App\Domain\SiteContent\Support\SiteContentFingerprint;
use App\Domain\SiteContent\Support\SiteContentSchema;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SiteContentWorkflow
{
    public function __construct(
        private SiteContentSchema $schema,
        private SiteContentFingerprint $fingerprint,
        private SiteContentTypeRegistry $types,
        private AnnouncementCollisionService $collisions,
        private RecordAuditEvent $audit,
        private PublicSiteContentCache $publicCache,
    ) {}

    public function submit(User $actor, SiteContent $content, string $note, string $fingerprint, bool $confirm = false, ?string $reason = null): SiteContentPublicationState
    {
        $permission = $this->permission($content, 'edit');
        Gate::forUser($actor)->authorize($permission);
        $note = $this->required($note, 'A submission note is required.');

        return DB::transaction(function () use ($actor, $content, $note, $fingerprint, $confirm, $reason, $permission): SiteContentPublicationState {
            [$locked, $state] = $this->lock($content);
            $this->assertFingerprint($locked, $state, $fingerprint);
            $revision = $this->revision($locked->current_draft_revision_id, $locked);
            $this->schema->validate($content->type, $revision->payload);
            $superseding = $state->candidate_revision_id !== null && $state->candidate_revision_id !== $revision->getKey();
            if ($superseding && ! $confirm) {
                throw new InvalidArgumentException('Explicit confirmation is required to supersede the governed candidate.');
            }
            if ($superseding && in_array($state->candidate_state, [CandidateState::Approved, CandidateState::Scheduled], true)) {
                $reason = $this->required((string) $reason, 'A reason is required to supersede an approved or scheduled candidate.');
            }
            $from = $state->candidate_state === null ? ($state->current_public_revision_id ? 'published' : 'draft') : $state->candidate_state->value;
            $previous = $state->candidate_revision_id;
            $state->forceFill([
                'candidate_revision_id' => $revision->getKey(),
                'candidate_state' => CandidateState::InReview,
                'submitted_by' => $actor->getKey(),
                'approved_by' => null,
                'approved_at' => null,
                'scheduled_by' => null,
                'scheduled_for' => null,
            ]);
            $this->transition(
                $state,
                $locked,
                $revision,
                $actor,
                $from,
                'in_review',
                $superseding ? 'site-content.review-candidate-superseded' : 'site-content.submitted-for-review',
                $permission,
                $note,
                $reason,
                ['superseded_revision_id' => $previous],
            );

            return $state->fresh();
        }, 3);
    }

    public function requestChanges(User $actor, SiteContent $content, string $note): SiteContentPublicationState
    {
        $permission = $this->permission($content, 'review');
        Gate::forUser($actor)->authorize($permission);
        $note = $this->required($note, 'A reviewer note is required.');

        return DB::transaction(function () use ($actor, $content, $note, $permission): SiteContentPublicationState {
            [$locked, $state] = $this->lock($content);
            $this->assertState($state, [CandidateState::InReview, CandidateState::Approved]);
            $revision = $this->candidate($locked, $state);
            $from = $state->candidate_state->value;
            $state->candidate_state = CandidateState::ChangesRequested;
            $state->approved_by = null;
            $state->approved_at = null;
            $this->transition($state, $locked, $revision, $actor, $from, 'changes_requested', 'site-content.changes-requested', $permission, $note);

            return $state->fresh();
        }, 3);
    }

    public function approve(User $actor, SiteContent $content, string $note, string $fingerprint): SiteContentPublicationState
    {
        $permission = $this->permission($content, 'approve');
        Gate::forUser($actor)->authorize($permission);

        return DB::transaction(function () use ($actor, $content, $note, $fingerprint, $permission): SiteContentPublicationState {
            [$locked, $state] = $this->lock($content);
            $this->assertFingerprint($locked, $state, $fingerprint);
            $this->assertState($state, [CandidateState::InReview]);
            if ($state->submitted_by === $actor->getKey() && ! $this->types->get($locked->type)->selfApprovalAllowed) {
                throw new InvalidArgumentException('This Site Content type requires a distinct approver.');
            }
            $revision = $this->candidate($locked, $state);
            $this->schema->validate($content->type, $revision->payload);
            $state->candidate_state = CandidateState::Approved;
            $state->approved_by = $actor->getKey();
            $state->approved_at = now('UTC');
            $this->transition($state, $locked, $revision, $actor, 'in_review', 'approved', 'site-content.approved', $permission, trim($note) ?: null);

            return $state->fresh();
        }, 3);
    }

    public function publish(User $actor, SiteContent $content, string $fingerprint): SiteContentPublicationState
    {
        $permission = $this->permission($content, 'publish');
        Gate::forUser($actor)->authorize($permission);

        return DB::transaction(function () use ($actor, $content, $fingerprint): SiteContentPublicationState {
            [$locked, $state] = $this->lock($content);
            $this->assertFingerprint($locked, $state, $fingerprint);

            return $this->publishLocked($actor, $locked, $state);
        }, 3);
    }

    public function schedule(User $actor, SiteContent $content, CarbonInterface $at, string $fingerprint): SiteContentPublicationState
    {
        $permission = $this->permission($content, 'schedule');
        Gate::forUser($actor)->authorize($permission);
        if (! $at->isFuture()) {
            throw new InvalidArgumentException('Schedule must be in the future.');
        }

        return DB::transaction(function () use ($actor, $content, $at, $fingerprint, $permission): SiteContentPublicationState {
            [$locked, $state] = $this->lock($content);
            $this->assertFingerprint($locked, $state, $fingerprint);
            $this->assertState($state, [CandidateState::Approved]);
            $revision = $this->candidate($locked, $state);
            $end = isset($revision->payload['effective_until']) && $revision->payload['effective_until'] ? CarbonImmutable::parse($revision->payload['effective_until'])->utc() : null;
            $this->collisions->assertAvailable($locked, $at, $end);
            $state->candidate_state = CandidateState::Scheduled;
            $state->scheduled_by = $actor->getKey();
            $state->scheduled_for = CarbonImmutable::instance($at)->utc();
            $this->transition($state, $locked, $revision, $actor, 'approved', 'scheduled', 'site-content.scheduled', $permission);

            return $state->fresh();
        }, 3);
    }

    public function cancelSchedule(User $actor, SiteContent $content, string $reason, string $fingerprint): SiteContentPublicationState
    {
        $permission = $this->permission($content, 'schedule');
        Gate::forUser($actor)->authorize($permission);
        $reason = $this->required($reason, 'A cancellation reason is required.');

        return DB::transaction(function () use ($actor, $content, $reason, $fingerprint, $permission): SiteContentPublicationState {
            [$locked, $state] = $this->lock($content);
            $this->assertFingerprint($locked, $state, $fingerprint);
            $this->assertState($state, [CandidateState::Scheduled]);
            $revision = $this->candidate($locked, $state);
            $state->candidate_state = CandidateState::Approved;
            $state->scheduled_for = null;
            $state->scheduled_by = null;
            $this->transition($state, $locked, $revision, $actor, 'scheduled', 'approved', 'site-content.schedule-cancelled', $permission, null, $reason);

            return $state->fresh();
        }, 3);
    }

    public function unpublish(User $actor, SiteContent $content, string $reason, string $fingerprint): SiteContentPublicationState
    {
        $permission = $this->permission($content, 'unpublish');
        Gate::forUser($actor)->authorize($permission);
        $reason = $this->required($reason, 'An unpublish reason is required.');

        return DB::transaction(function () use ($actor, $content, $reason, $fingerprint, $permission): SiteContentPublicationState {
            [$locked, $state] = $this->lock($content);
            $this->assertFingerprint($locked, $state, $fingerprint);
            if ($state->current_public_revision_id === null) {
                throw new InvalidArgumentException('No Site Content revision is designated published.');
            }
            $revision = $this->revision($state->current_public_revision_id, $locked);
            $state->current_public_revision_id = null;
            $this->transition($state, $locked, $revision, $actor, 'published', 'unpublished', 'site-content.unpublished', $permission, null, $reason);

            return $state->fresh();
        }, 3);
    }

    public function publishScheduled(SiteContent $content, string $jobIdentity, ?CarbonInterface $now = null): bool
    {
        return DB::transaction(function () use ($content, $jobIdentity, $now): bool {
            [$locked, $state] = $this->lock($content);
            $now ??= now('UTC');
            if ($locked->archived_at !== null) {
                return false;
            }
            if ($state->candidate_state !== CandidateState::Scheduled || $state->scheduled_for === null || $state->scheduled_for->isAfter($now)) {
                return false;
            }
            if (SiteContentPublicationTransition::query()->where('job_identity', $jobIdentity)->exists()) {
                return false;
            }
            $actor = User::query()->findOrFail($state->scheduled_by);
            $candidate = $this->candidate($locked, $state);
            $end = isset($candidate->payload['effective_until']) && $candidate->payload['effective_until'] ? CarbonImmutable::parse($candidate->payload['effective_until'])->utc() : null;
            $this->collisions->assertAvailable($locked, $state->scheduled_for, $end);
            $this->publishLocked($actor, $locked, $state, $jobIdentity);

            return true;
        }, 3);
    }

    private function permission(SiteContent $content, string $action): string
    {
        return $this->types->get($content->type)->permission($action);
    }

    /** @return array{SiteContent, SiteContentPublicationState} */
    private function lock(SiteContent $content): array
    {
        $locked = SiteContent::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail();
        $state = SiteContentPublicationState::query()->firstOrCreate(['site_content_id' => $locked->getKey()]);
        $state = SiteContentPublicationState::query()->whereKey($state->getKey())->lockForUpdate()->firstOrFail();
        $state->load('candidateRevision');
        $locked->setRelation('publicationState', $state);

        return [$locked, $state];
    }

    private function publishLocked(User $actor, SiteContent $content, SiteContentPublicationState $state, ?string $jobIdentity = null): SiteContentPublicationState
    {
        $permission = $this->permission($content, 'publish');
        $this->assertState($state, [$jobIdentity ? CandidateState::Scheduled : CandidateState::Approved]);
        $revision = $this->candidate($content, $state);
        $this->schema->validate($content->type, $revision->payload);
        $from = $state->candidate_state->value;
        $state->forceFill([
            'current_public_revision_id' => $revision->getKey(),
            'candidate_revision_id' => null,
            'candidate_state' => null,
            'submitted_by' => null,
            'approved_by' => null,
            'approved_at' => null,
            'scheduled_by' => null,
            'scheduled_for' => null,
        ]);
        $this->transition($state, $content, $revision, $actor, $from, 'published', 'site-content.published', $permission, null, null, [], $jobIdentity);

        return $state->fresh();
    }

    private function candidate(SiteContent $content, SiteContentPublicationState $state): ContentRevision
    {
        return $this->revision((string) $state->candidate_revision_id, $content);
    }

    private function revision(string $id, SiteContent $content): ContentRevision
    {
        $revision = ContentRevision::query()->whereKey($id)->lockForUpdate()->firstOrFail();
        if ($revision->resource_type !== SiteContent::class || $revision->resource_id !== $content->getKey()) {
            throw new InvalidArgumentException('Site Content revision does not belong to this aggregate.');
        }

        return $revision;
    }

    /** @param list<CandidateState> $allowed */
    private function assertState(SiteContentPublicationState $state, array $allowed): void
    {
        if (! in_array($state->candidate_state, $allowed, true)) {
            throw new InvalidArgumentException('Site Content transition is invalid from the current state.');
        }
    }

    private function assertFingerprint(SiteContent $content, SiteContentPublicationState $state, string $fingerprint): void
    {
        if (! hash_equals($this->fingerprint->for($content, $state), $fingerprint)) {
            throw new StalePublicationStateException;
        }
    }

    private function required(string $value, string $message): string
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException($message);
        }

        return trim($value);
    }

    /** @param array<string, mixed> $extra */
    private function transition(
        SiteContentPublicationState $state,
        SiteContent $content,
        ContentRevision $revision,
        User $actor,
        ?string $from,
        string $to,
        string $action,
        string $permission,
        ?string $note = null,
        ?string $reason = null,
        array $extra = [],
        ?string $jobIdentity = null,
    ): void {
        $correlation = (string) Str::ulid();
        $state->state_version++;
        $state->last_transition_at = now('UTC');
        $state->save();
        SiteContentPublicationTransition::query()->create([
            'site_content_id' => $content->getKey(),
            'publication_state_id' => $state->getKey(),
            'from_state' => $from,
            'to_state' => $to,
            'revision_id' => $revision->getKey(),
            'actor_id' => $actor->getKey(),
            'note' => $note,
            'reason' => $reason,
            'scheduled_for' => $state->scheduled_for,
            'job_identity' => $jobIdentity,
            'correlation_id' => $correlation,
            'occurred_at' => now('UTC'),
        ]);
        if (in_array($action, ['site-content.published', 'site-content.unpublished'], true)) {
            $resourceId = (string) $content->getKey();
            DB::afterCommit(fn () => $this->publicCache->invalidate($resourceId));
        }
        $this->audit->handle($action, $content, $actor, ['state' => $from], array_merge([
            'state' => $to,
            'revision_id' => $revision->getKey(),
            'checksum' => substr($revision->checksum, 0, 12),
            'public_revision_id' => $state->current_public_revision_id,
            'job_identity' => $jobIdentity,
        ], $extra), $permission, $reason ?? $note, $correlation);
    }
}
