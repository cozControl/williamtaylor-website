<?php

namespace App\Domain\PublicProjection\Services;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;

final class InvalidatePublicPagesUsingMedia
{
    public function __construct(private PublicPageProjectionCache $cache) {}

    public function handle(MediaAsset $asset): void
    {
        $revisionIds = MediaUsage::query()->where('media_asset_id', $asset->getKey())->where('owner_type', ContentRevision::class)->pluck('owner_identifier');
        if ($revisionIds->isEmpty()) {
            return;
        }
        $pageIds = ContentRevision::query()->whereKey($revisionIds)->where('resource_type', Page::class)->pluck('resource_id')->unique();
        foreach ($pageIds as $pageId) {
            $this->cache->invalidate((string) $pageId);
        }
    }
}
