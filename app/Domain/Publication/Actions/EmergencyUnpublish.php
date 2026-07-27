<?php

namespace App\Domain\Publication\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Publication\Data\EmergencyUnpublishResult;
use App\Domain\PublicProjection\Services\PublicPageProjectionCache;
use App\Domain\PublicProjection\Services\PublicSiteContentCache;
use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Models\SiteContentPublicationState;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EmergencyUnpublish
{
    public function __construct(private RecordAuditEvent $audit, private PublicPageProjectionCache $pages, private PublicSiteContentCache $siteContent) {}

    public function handle(User $actor, Model $resource, string $reason): EmergencyUnpublishResult
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH);
        if (! $actor->hasVerifiedEmail()) {
            throw new AuthorizationException('Verified email is required.');
        }
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A bounded emergency reason is required.');
        }

        return DB::transaction(function () use ($actor, $resource, $reason): EmergencyUnpublishResult {
            [$key, $locked, $state] = match (true) {
                $resource instanceof Page => $this->lockPage($resource),
                $resource instanceof SiteContent => $this->lockSiteContent($resource),
                default => throw new InvalidArgumentException('Resource does not support emergency unpublish.'),
            };
            $previous = $state->current_public_revision_id;
            $scheduled = $state->candidate_state === CandidateState::Scheduled;
            if ($previous === null && ! $scheduled) {
                return new EmergencyUnpublishResult($key, (string) $locked->getKey(), false, null, '');
            }
            $correlation = (string) Str::ulid();
            $state->current_public_revision_id = null;
            if ($scheduled) {
                $state->candidate_state = CandidateState::Approved;
                $state->scheduled_for = null;
                $state->scheduled_by = null;
            }
            $state->state_version++;
            $state->last_transition_at = now('UTC');
            $state->save();
            $this->audit->handle('publication.emergency-unpublished', $locked, $actor, ['public_revision_id' => $previous], ['public_revision_id' => null, 'scheduled_cancelled' => $scheduled], PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH, $reason, $correlation);
            DB::afterCommit(fn () => $key === 'page' ? $this->pages->invalidate((string) $locked->getKey()) : $this->siteContent->invalidate((string) $locked->getKey()));

            return new EmergencyUnpublishResult($key, (string) $locked->getKey(), true, $previous, $correlation);
        }, 3);
    }

    /** @return array{string, Page, PagePublicationState} */
    private function lockPage(Page $page): array
    {
        return ['page', Page::query()->whereKey((string) $page->getKey())->lockForUpdate()->firstOrFail(), PagePublicationState::query()->where('page_id', $page->getKey())->lockForUpdate()->firstOrFail()];
    }

    /** @return array{string, SiteContent, SiteContentPublicationState} */
    private function lockSiteContent(SiteContent $content): array
    {
        return ['site_content', SiteContent::query()->whereKey((string) $content->getKey())->lockForUpdate()->firstOrFail(), SiteContentPublicationState::query()->where('site_content_id', $content->getKey())->lockForUpdate()->firstOrFail()];
    }
}
