<?php

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ArchivePage
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(User $actor, Page $page, string $reason): void
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_ARCHIVE);
        if (trim($reason) === '') {
            throw new InvalidArgumentException('An archive reason is required.');
        }
        DB::transaction(function () use ($actor, $page, $reason): void {
            $locked = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->archived_at !== null) {
                return;
            }            $publication = PagePublicationState::query()->where('page_id', $locked->getKey())->lockForUpdate()->first();
            if ($publication?->candidate_state === CandidateState::Scheduled) {
                throw new InvalidArgumentException('Cancel the scheduled publication before archiving this Page.');
            }
            if ($publication?->current_public_revision_id !== null) {
                throw new InvalidArgumentException('Unpublish the designated revision before archiving this Page.');
            }
            $locked->forceFill(['archived_at' => now('UTC'), 'updated_by' => $actor->getKey()])->save();
            $this->audit->handle('content.page.archived', $locked, $actor, ['state' => 'active_draft'], ['state' => 'archived'], PermissionRegistry::PAGES_ARCHIVE, $reason);
        }, 3);
    }
}
