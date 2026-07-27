<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Media\Models\MediaUsage;

final class CollectionStateFingerprint
{
    public function identity(Collection $collection): string
    {
        return $this->hash(['id' => $collection->id, 'type' => $collection->collection_type, 'slug' => $collection->slug, 'revision' => $collection->current_draft_revision_id, 'lock' => $collection->lock_version, 'archived' => $collection->archived_at !== null]);
    }

    public function memberships(string $collectionId): string
    {
        $rows = CollectionProduct::query()->active()->where('collection_id', $collectionId)->orderBy('id')->get(['id', 'product_id', 'position'])->map->only(['id', 'product_id', 'position'])->all();

        return $this->hash($rows);
    }

    public function media(string $collectionId): string
    {
        $rows = MediaUsage::query()->where('owner_type', Collection::class)->where('owner_identifier', $collectionId)->orderBy('id')->get(['id', 'media_asset_id', 'field_role', 'sort_order', 'alt_text_override', 'decorative_override'])->map->only(['id', 'media_asset_id', 'field_role', 'sort_order', 'alt_text_override', 'decorative_override'])->all();

        return $this->hash($rows);
    }

    private function hash(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
