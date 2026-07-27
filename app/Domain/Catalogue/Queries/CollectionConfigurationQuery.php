<?php

namespace App\Domain\Catalogue\Queries;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\CollectionReadinessEvaluator;
use App\Domain\Media\Models\MediaUsage;

final class CollectionConfigurationQuery
{
    public function __construct(private CollectionConfigurationCache $cache, private CollectionReadinessEvaluator $readiness) {}

    /** @return array<string, mixed> */
    public function find(string $collectionId): array
    {
        return $this->cache->remember($collectionId, function () use ($collectionId): array {
            $collection = Collection::query()->with(['currentDraftRevision', 'products.product'])->findOrFail($collectionId);
            $result = $this->readiness->evaluate($collection);
            $media = MediaUsage::query()->where('owner_type', Collection::class)->where('owner_identifier', $collectionId)->orderBy('field_role')->orderBy('sort_order')->get();

            return [
                'id' => $collection->id, 'type' => $collection->collection_type, 'slug' => $collection->slug,
                'status' => $collection->catalogue_status, 'archived' => $collection->archived_at !== null,
                'revision' => $collection->currentDraftRevision?->only(['id', 'revision_number', 'title', 'short_description', 'checksum']),
                'products' => $collection->products->whereNull('archived_at')->map(fn ($item): array => ['membership_id' => $item->id, 'product_id' => $item->product_id, 'position' => $item->position, 'slug' => $item->product?->slug])->values()->all(),
                'media' => $media->map(fn ($item): array => ['usage_id' => $item->id, 'asset_id' => $item->media_asset_id, 'role' => $item->field_role, 'position' => $item->sort_order, 'alt_override' => $item->alt_text_override])->all(),
                'readiness' => ['ready' => $result->ready, 'failures' => $result->failureCodes],
            ];
        });
    }
}
