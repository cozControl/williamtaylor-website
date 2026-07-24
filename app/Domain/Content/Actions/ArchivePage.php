<?php

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Support\PermissionRegistry;
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
            }
            $locked->forceFill(['archived_at' => now('UTC'), 'updated_by' => $actor->getKey()])->save();
            $this->audit->handle('content.page.archived', $locked, $actor, ['state' => 'active_draft'], ['state' => 'archived'], PermissionRegistry::PAGES_ARCHIVE, $reason);
        }, 3);
    }
}
