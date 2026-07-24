<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

final class CreatePagePreviewUrl
{
    public function handle(User $actor, Page $page, ContentRevision $revision): string
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_PREVIEW);
        abort_unless($revision->resource_type === Page::class && $revision->resource_id === $page->getKey(), 404);

        return URL::temporarySignedRoute('preview.pages.show', now()->addMinutes(15), ['page' => $page, 'revision' => $revision]);
    }
}
