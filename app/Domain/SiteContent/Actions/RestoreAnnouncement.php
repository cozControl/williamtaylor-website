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

final class RestoreAnnouncement
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(User $actor, SiteContent $announcement, string $reason): SiteContent
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::ANNOUNCEMENTS_RESTORE);
        if ($announcement->type !== SiteContentTypeRegistry::ANNOUNCEMENT) {
            throw new InvalidArgumentException('Only announcements may be restored.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A restore reason is required.');
        }

        return DB::transaction(function () use ($actor, $announcement, $reason): SiteContent {
            $locked = SiteContent::query()->whereKey($announcement->getKey())->lockForUpdate()->firstOrFail();
            $locked->forceFill(['archived_at' => null, 'archived_by' => null])->save();
            $this->audit->handle('site-content.restored', $locked, $actor, null, ['type' => $locked->type], PermissionRegistry::ANNOUNCEMENTS_RESTORE, $reason);

            return $locked->fresh();
        }, 3);
    }
}
