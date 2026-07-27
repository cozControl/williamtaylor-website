<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductBadge;

final class ProductBadgeSetFingerprint
{
    public function for(Product $product): string
    {
        $items = ProductBadge::query()->where('product_id', $product->id)->orderBy('id')
            ->get(['id', 'badge_key', 'position', 'archived_at'])
            ->map(fn (ProductBadge $badge): array => [
                'id' => $badge->id, 'key' => $badge->badge_key, 'position' => $badge->position,
                'archived' => $badge->archived_at !== null,
            ])->all();

        return hash('sha256', json_encode([
            'product' => $product->id, 'product_lock' => $product->lock_version,
            'product_archived' => $product->archived_at !== null, 'items' => $items,
        ], JSON_THROW_ON_ERROR));
    }
}
