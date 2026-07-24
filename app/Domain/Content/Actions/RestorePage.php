<?php

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\PageTypeRegistry;
use App\Domain\Content\Support\TemplateRegistry;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

final class RestorePage
{
    public function __construct(private RecordAuditEvent $audit, private PageTypeRegistry $types, private TemplateRegistry $templates) {}

    public function handle(User $actor, Page $page, string $reason): void
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_RESTORE);
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A restore reason is required.');
        }
        DB::transaction(function () use ($actor, $page, $reason): void {
            $locked = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            $type = $this->types->get($locked->type);
            $this->templates->get($locked->template_key);
            if (! in_array($locked->template_key, $type['templates'], true)) {
                throw new InvalidArgumentException('The archived page is no longer compatible with its template.');
            }
            if ($locked->archived_at === null) {
                return;
            }
            $locked->forceFill(['archived_at' => null, 'updated_by' => $actor->getKey()])->save();
            $this->audit->handle('content.page.restored', $locked, $actor, ['state' => 'archived'], ['state' => 'active_draft'], PermissionRegistry::PAGES_RESTORE, $reason);
        }, 3);
    }
}
