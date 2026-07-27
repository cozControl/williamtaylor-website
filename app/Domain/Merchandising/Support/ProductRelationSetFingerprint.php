<?php

namespace App\Domain\Merchandising\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Merchandising\Models\ProductRelation;

final class ProductRelationSetFingerprint
{
    public function for(Product $source, string $kind): string
    {
        $items = ProductRelation::query()->where('source_product_id', $source->id)->where('relation_kind', $kind)
            ->orderBy('id')->get(['id', 'target_product_id', 'position', 'archived_at'])
            ->map(fn (ProductRelation $relation): array => [
                'id' => $relation->id, 'target' => $relation->target_product_id,
                'position' => $relation->position, 'archived' => $relation->archived_at !== null,
            ])->all();

        return hash('sha256', json_encode([
            'source' => $source->id, 'source_lock' => $source->lock_version,
            'source_archived' => $source->archived_at !== null, 'kind' => $kind, 'items' => $items,
        ], JSON_THROW_ON_ERROR));
    }
}
