<?php

namespace App\Domain\Merchandising\Support;

use App\Domain\Merchandising\Models\ProductPlacement;

final class ProductPlacementSetFingerprint
{
    public function for(string $slot): string
    {
        $items = ProductPlacement::query()->where('slot_key', $slot)->orderBy('id')
            ->get(['id', 'product_id', 'position', 'archived_at'])
            ->map(fn (ProductPlacement $placement): array => [
                'id' => $placement->id, 'product' => $placement->product_id,
                'position' => $placement->position, 'archived' => $placement->archived_at !== null,
            ])->all();

        return hash('sha256', json_encode(['slot' => $slot, 'items' => $items], JSON_THROW_ON_ERROR));
    }
}
