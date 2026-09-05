<?php

namespace App\Domain\Publishing\Services;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\PublicProjection\Services\PublicPageProjectionCache;
use App\Domain\Publishing\Contracts\PublicationPolicy;
use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\Publishing\Events\PageRevisionPublished;
use App\Domain\Publishing\Exceptions\StalePublicationStateException;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\Publishing\Models\PagePublicationTransition;
use App\Domain\Publishing\Support\PublicationFingerprint;
use App\Domain\Publishing\Support\PublicationReadiness;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class PagePublishingWorkflow
{
    public function __construct(
        private PublicationReadiness $readiness,
        private PublicationFingerprint $fingerprints,
        private PublicationPolicy $policy,
        private RecordAuditEvent $audit,
        private PublicPageProjectionCache $publicPageCache,
    ) {}

    public function submit(User $actor, Page $page, string $note, string $fingerprint, bool $confirmSupersession = false, ?string $reason = null): PagePublicationState
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_EDIT);
        $note = $this->required($note, 'A submission note is required.');

        return DB::transaction(function () use ($actor, $page, $note, $fingerprint, $confirmSupersession, $reason): PagePublicationState {
            [$locked, $state] = $this->lock($page);
            $this->assertActive($locked);
            $this->assertFingerprint($locked, $state, $fingerprint);
            $revision = ContentRevision::query()->whereKey($locked->current_draft_revision_id)->lockForUpdate()->firstOrFail();
            $this->readiness->ensureReady($locked, $revision);
            $superseding = $state->candidate_revision_id !== null && $state->candidate_revision_id !== $revision->getKey();
            if ($superseding && ! $confirmSupersession) {
                throw new InvalidArgumentException('Explicit confirmation is required to supersede the current candidate.');
            }
            if ($superseding && in_array($state->candidate_state, [CandidateState::Approved, CandidateState::Scheduled], true)) {
                $reason = $this->required((string) $reason, 'A reason is required to supersede an approved or scheduled candidate.');
            }
            $from = $this->label($state);
            $previousRevision = $state->candidate_revision_id;
            $state->forceFill([
                'candidate_revision_id' => $revision->getKey(),
                'candidate_state' => CandidateState::InReview,
                'submitted_by' => $actor->getKey(),
                'approved_by' => null,
                'approved_at' => null,
                'scheduled_by' => null,
                'scheduled_for' => null,
            ]);
            $action = $superseding ? 'content.page.review-candidate-superseded' : 'content.page.submitted-for-review';
            $permission = PermissionRegistry::PAGES_EDIT;
            $this->transition($state, $locked, $revision, $actor, $from, 'in_review', $action, $permission, $note, $reason, [
                'superseded_revision_id' => $previousRevision,
            ]);

            return $state->fresh(['candidateRevision', 'currentPublicRevision']);
        }, 3);
    }

    public function requestChanges(User $actor, Page $page, string $note): PagePublicationState
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_REVIEW);
        $note = $this->required($note, 'A reviewer note is required.');

        return DB::transaction(function () use ($actor, $page, $note): PagePublicationState {
            [$locked, $state] = $this->lock($page);
            $this->assertActive($locked);
            $this->assertCandidateState($state, [CandidateState::InReview, CandidateState::Approved]);
            $revision = $this->candidate($locked, $state);
            $from = $state->candidate_state->value;
            $state->candidate_state = CandidateState::ChangesRequested;
            $state->approved_by = null;
            $state->approved_at = null;
            $this->transition($state, $locked, $revision, $actor, $from, 'changes_requested', 'content.page.changes-requested', PermissionRegistry::PAGES_REVIEW, $note);

            return $state->fresh();
        }, 3);
    }

    public function approve(User $actor, Page $page, string $note, string $fingerprint): PagePublicationState
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_APPROVE);

        return DB::transaction(function () use ($actor, $page, $note, $fingerprint): PagePublicationState {
            [$locked, $state] = $this->lock($page);
            $this->assertActive($locked);
            $this->assertFingerprint($locked, $state, $fingerprint);
            $this->assertCandidateState($state, [CandidateState::InReview]);
            $revision = $this->candidate($locked, $state);
            $this->readiness->ensureReady($locked, $revision);
            if ($state->submitted_by === $actor->getKey() && ! $this->policy->allowsSelfApproval($locked->type)) {
                throw new InvalidArgumentException('This Page type requires a distinct approver.');
            }
            $state->candidate_state = CandidateState::Approved;
            $state->approved_by = $actor->getKey();
            $state->approved_at = now('UTC');
            $this->transition($state, $locked, $revision, $actor, 'in_review', 'approved', 'content.page.approved', PermissionRegistry::PAGES_APPROVE, trim($note) ?: null);

            return $state->fresh();
        }, 3);
    }

    public function publishNow(User $actor, Page $page, string $fingerprint): PagePublicationState
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_PUBLISH);

        return DB::transaction(function () use ($actor, $page, $fingerprint): PagePublicationState {
            [$locked, $state] = $this->lock($page);
            $this->assertActive($locked);
            $this->assertFingerprint($locked, $state, $fingerprint);
            $this->assertCandidateState($state, [CandidateState::Approved]);

            return $this->publishLocked($actor, $locked, $state, null);
        }, 3);
    }

    public function makeCurrentDraftVisible(User $actor, Page $page): PagePublicationState
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_EDIT);
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_PUBLISH);

        return DB::transaction(function () use ($actor, $page): PagePublicationState {
            [$locked, $state] = $this->lock($page);
            $this->assertActive($locked);
            $revision = ContentRevision::query()->whereKey($locked->current_draft_revision_id)->lockForUpdate()->firstOrFail();
            abort_unless($revision->resource_type === Page::class && $revision->resource_id === $locked->getKey(), 404);
            $this->readiness->ensureReady($locked, $revision);
            $from = $this->label($state);
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
            $this->transition($state, $locked, $revision, $actor, $from, 'published', 'content.page.saved-visible', PermissionRegistry::PAGES_PUBLISH);
            $pageId = (string) $locked->getKey();
            DB::afterCommit(function () use ($locked, $revision, $pageId): void {
                event(new PageRevisionPublished($locked->getKey(), $revision->getKey()));
                $this->publicPageCache->invalidate($pageId);
            });

            return $state->fresh();
        }, 3);
    }

    public function makeHidden(User $actor, Page $page): PagePublicationState
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_EDIT);
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_UNPUBLISH);

        return DB::transaction(function () use ($actor, $page): PagePublicationState {
            [$locked, $state] = $this->lock($page);
            if ($state->current_public_revision_id === null) {
                return $state;
            }
            $revision = ContentRevision::query()->whereKey($state->current_public_revision_id)->lockForUpdate()->firstOrFail();
            $state->current_public_revision_id = null;
            $this->transition($state, $locked, $revision, $actor, 'published', 'hidden', 'content.page.saved-hidden', PermissionRegistry::PAGES_UNPUBLISH);
            $pageId = (string) $locked->getKey();
            DB::afterCommit(fn () => $this->publicPageCache->invalidate($pageId));

            return $state->fresh();
        }, 3);
    }

    public function schedule(User $actor, Page $page, CarbonInterface $scheduledForUtc, string $fingerprint): PagePublicationState
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_SCHEDULE);
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_PUBLISH);
        if (! $scheduledForUtc->isFuture()) {
            throw new InvalidArgumentException('Scheduled publication must be in the future.');
        }

        return DB::transaction(function () use ($actor, $page, $scheduledForUtc, $fingerprint): PagePublicationState {
            [$locked, $state] = $this->lock($page);
            $this->assertActive($locked);
            $this->assertFingerprint($locked, $state, $fingerprint);
            $this->assertCandidateState($state, [CandidateState::Approved]);
            $revision = $this->candidate($locked, $state);
            $this->readiness->ensureReady($locked, $revision);
            $state->candidate_state = CandidateState::Scheduled;
            $state->scheduled_for = CarbonImmutable::instance($scheduledForUtc)->utc();
            $state->scheduled_by = $actor->getKey();
            $this->transition($state, $locked, $revision, $actor, 'approved', 'scheduled', 'content.page.scheduled', PermissionRegistry::PAGES_SCHEDULE, null, null, [
                'scheduled_for' => $state->scheduled_for->toIso8601String(),
            ]);

            return $state->fresh();
        }, 3);
    }

    public function cancelSchedule(User $actor, Page $page, string $reason, string $fingerprint): PagePublicationState
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_SCHEDULE);
        $reason = $this->required($reason, 'A schedule cancellation reason is required.');

        return DB::transaction(function () use ($actor, $page, $reason, $fingerprint): PagePublicationState {
            [$locked, $state] = $this->lock($page);
            $this->assertFingerprint($locked, $state, $fingerprint);
            $this->assertCandidateState($state, [CandidateState::Scheduled]);
            $revision = $this->candidate($locked, $state);
            $scheduledFor = $state->scheduled_for;
            $state->candidate_state = CandidateState::Approved;
            $state->scheduled_for = null;
            $state->scheduled_by = null;
            $this->transition($state, $locked, $revision, $actor, 'scheduled', 'approved', 'content.page.schedule-cancelled', PermissionRegistry::PAGES_SCHEDULE, null, $reason, [
                'cancelled_schedule' => $scheduledFor?->toIso8601String(),
            ]);

            return $state->fresh();
        }, 3);
    }

    public function unpublish(User $actor, Page $page, string $reason, string $fingerprint): PagePublicationState
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_UNPUBLISH);
        $reason = $this->required($reason, 'An unpublish reason is required.');

        return DB::transaction(function () use ($actor, $page, $reason, $fingerprint): PagePublicationState {
            [$locked, $state] = $this->lock($page);
            $this->assertFingerprint($locked, $state, $fingerprint);
            if ($state->current_public_revision_id === null) {
                throw new InvalidArgumentException('This Page has no designated published revision.');
            }
            $revision = ContentRevision::query()->whereKey($state->current_public_revision_id)->firstOrFail();
            $state->current_public_revision_id = null;
            $this->transition($state, $locked, $revision, $actor, 'published', 'unpublished', 'content.page.unpublished', PermissionRegistry::PAGES_UNPUBLISH, null, $reason);
            $pageId = (string) $locked->getKey();
            DB::afterCommit(fn () => $this->publicPageCache->invalidate($pageId));

            return $state->fresh();
        }, 3);
    }

    public function publishScheduled(Page $page, string $jobIdentity, ?CarbonInterface $now = null): bool
    {
        $now ??= now('UTC');

        return DB::transaction(function () use ($page, $jobIdentity, $now): bool {
            [$locked, $state] = $this->lock($page);
            if ($locked->archived_at !== null || $state->candidate_state !== CandidateState::Scheduled || $state->scheduled_for === null || $state->scheduled_for->isAfter($now)) {
                return false;
            }
            if (PagePublicationTransition::query()->where('job_identity', $jobIdentity)->exists()) {
                return false;
            }
            $actor = User::query()->findOrFail($state->scheduled_by);
            $this->publishLocked($actor, $locked, $state, $jobIdentity);

            return true;
        }, 3);
    }

    /** @return array{Page, PagePublicationState} */
    private function lock(Page $page): array
    {
        $locked = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
        $state = PagePublicationState::query()->firstOrCreate(['page_id' => $locked->getKey()]);
        $state = PagePublicationState::query()->whereKey($state->getKey())->lockForUpdate()->firstOrFail();
        $state->load(['candidateRevision', 'currentPublicRevision']);
        $locked->setRelation('publicationState', $state);

        return [$locked, $state];
    }

    private function publishLocked(User $actor, Page $page, PagePublicationState $state, ?string $jobIdentity): PagePublicationState
    {
        $this->assertCandidateState($state, [$jobIdentity === null ? CandidateState::Approved : CandidateState::Scheduled]);
        $revision = $this->candidate($page, $state);
        $this->readiness->ensureReady($page, $revision);
        if ($state->submitted_by === $state->approved_by && ! $this->policy->allowsSelfApproval($page->type)) {
            throw new InvalidArgumentException('Publication policy no longer permits self approval.');
        }
        $from = $state->candidate_state->value;
        $state->current_public_revision_id = $revision->getKey();
        $state->candidate_revision_id = null;
        $state->candidate_state = null;
        $state->submitted_by = null;
        $state->approved_by = null;
        $state->approved_at = null;
        $state->scheduled_by = null;
        $state->scheduled_for = null;
        $this->transition($state, $page, $revision, $actor, $from, 'published', 'content.page.published', PermissionRegistry::PAGES_PUBLISH, null, null, [], $jobIdentity);
        $pageId = (string) $page->getKey();
        DB::afterCommit(function () use ($page, $revision, $pageId): void {
            event(new PageRevisionPublished($page->getKey(), $revision->getKey()));
            $this->publicPageCache->invalidate($pageId);
        });

        return $state->fresh();
    }

    private function candidate(Page $page, PagePublicationState $state): ContentRevision
    {
        $revision = ContentRevision::query()->whereKey($state->candidate_revision_id)->lockForUpdate()->firstOrFail();
        if ($revision->resource_type !== Page::class || $revision->resource_id !== $page->getKey()) {
            throw new InvalidArgumentException('Candidate revision is invalid.');
        }

        return $revision;
    }

    /** @param list<CandidateState> $allowed */
    private function assertCandidateState(PagePublicationState $state, array $allowed): void
    {
        if (! in_array($state->candidate_state, $allowed, true)) {
            throw new InvalidArgumentException('Publishing transition is not valid from the current state.');
        }
    }

    private function assertActive(Page $page): void
    {
        if ($page->archived_at !== null) {
            throw new InvalidArgumentException('Archived Pages cannot enter publishing workflow.');
        }
    }

    private function assertFingerprint(Page $page, PagePublicationState $state, string $fingerprint): void
    {
        if (! hash_equals($this->fingerprints->for($page, $state), $fingerprint)) {
            throw new StalePublicationStateException;
        }
    }

    private function required(string $value, string $message): string
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }

    private function label(PagePublicationState $state): string
    {
        if ($state->candidate_state !== null) {
            return $state->candidate_state->value;
        }

        return $state->current_public_revision_id === null ? 'draft' : 'published';
    }

    /** @param array<string, mixed> $extra */
    private function transition(
        PagePublicationState $state,
        Page $page,
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
        PagePublicationTransition::query()->create([
            'page_id' => $page->getKey(),
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
        $this->audit->handle(
            $action,
            $page,
            $actor,
            ['state' => $from],
            array_merge([
                'state' => $to,
                'revision_id' => $revision->getKey(),
                'candidate_checksum' => substr($revision->checksum, 0, 12),
                'current_public_revision_id' => $state->current_public_revision_id,
                'job_identity' => $jobIdentity,
            ], $extra),
            $permission,
            $reason ?? $note,
            $correlation,
        );
    }
}
