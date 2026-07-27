<?php

namespace App\Domain\SiteContent\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class ArchiveAnnouncement
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(User $actor, SiteContent $announcement, string $reason): SiteContent
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::ANNOUNCEMENTS_ARCHIVE);
        if ($announcement->type !== SiteContentTypeRegistry::ANNOUNCEMENT) {
            throw new InvalidArgumentException('Only announcements may be archived.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('An archive reason is required.');
        }

        return DB::transaction(function () use ($actor, $announcement, $reason): SiteContent {
            $locked = SiteContent::query()->whereKey($announcement->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->publicationState?->current_public_revision_id !== null) {
                throw new InvalidArgumentException('Unpublish the announcement before archiving it.');
            }
            $locked->forceFill(['archived_at' => now('UTC'), 'archived_by' => $actor->getKey()])->save();
            $this->audit->handle('site-content.archived', $locked, $actor, null, ['type' => $locked->type], PermissionRegistry::ANNOUNCEMENTS_ARCHIVE, $reason);

            return $locked->fresh();
        }, 3);
    }
}
