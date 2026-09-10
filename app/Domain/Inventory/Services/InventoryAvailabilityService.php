<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Models\StockLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

final class InventoryAvailabilityService
{
    /** @param list<string> $productIds
     * @return array<string, array<string, array{available_to_sell:int, is_available:bool}>>
     */
    public function storefront(array $productIds): array
    {
        $cache = request()->attributes->get('inventory.storefront', []);
        $missing = array_values(array_diff($productIds, array_keys($cache)));
        if ($missing !== []) {
            foreach ($missing as $id) {
                $cache[$id] = [];
            }
            if (Schema::hasTable('inventory_balances')) {
                $location = StockLocation::query()->where('code', 'MAIN')->first();
                if ($location !== null) {
                    $variants = ProductVariant::query()->active()->whereIn('product_id', $missing)->with(['product', 'values.option'])->get();
                    $summaries = $this->summaries($variants, $location);
                    foreach ($variants as $variant) {
                        $available = $summaries[$variant->id]['available'];
                        $cache[$variant->product_id][$variant->id] = ['available_to_sell' => $available, 'is_available' => $available > 0];
                    }
                }
            }
            request()->attributes->set('inventory.storefront', $cache);
        }

        return array_intersect_key($cache, array_flip($productIds));
    }

    public function onHand(ProductVariant $variant, ?StockLocation $location = null): int
    {
        $location ??= StockLocation::main();

        return (int) (InventoryBalance::query()->where('variant_id', $variant->id)->where('stock_location_id', $location->id)->value('on_hand') ?? 0);
    }

    public function availableToSell(ProductVariant $variant, ?StockLocation $location = null): int
    {
        $location = ($location ?? StockLocation::main())->fresh();
        $variant = $variant->fresh();
        if ($location === null || ! $location->active || ! $location->fulfillment_enabled || $variant === null || $variant->archived_at !== null || ($variant->product === null || $variant->product->archived_at !== null) || blank($variant->sku) || ! $this->optionsEligible($variant)) {
            return 0;
        }

        return max(0, $this->onHand($variant, $location) - app(InventoryReservationService::class)->activeQuantity($variant->id, $location->id));
    }

    /** @param iterable<ProductVariant> $variants
     * @return array<string, array{on_hand: int, available: int}>
     */
    public function summaries(iterable $variants, ?StockLocation $location = null): array
    {
        $location ??= StockLocation::main();
        $variants = new Collection(collect($variants)->all());
        $variants->loadMissing(['product', 'values.option']);
        $balances = InventoryBalance::query()->where('stock_location_id', $location->id)->whereIn('variant_id', $variants->pluck('id'))->pluck('on_hand', 'variant_id');
        $reserved = InventoryReservation::query()->where('stock_location_id', $location->id)->whereIn('variant_id', $variants->pluck('id'))->where('status', 'active')->selectRaw('variant_id, SUM(quantity) as reserved')->groupBy('variant_id')->pluck('reserved', 'variant_id');
        $result = [];
        foreach ($variants as $variant) {
            $onHand = (int) ($balances[$variant->id] ?? 0);
            $eligible = $location->active && $location->fulfillment_enabled && $variant->archived_at === null && $variant->product !== null && $variant->product->archived_at === null && filled($variant->sku) && $this->optionsEligible($variant);
            $result[$variant->id] = ['on_hand' => $onHand, 'available' => $eligible ? max(0, $onHand - (int) ($reserved[$variant->id] ?? 0)) : 0];
        }

        return $result;
    }

    public function productHasAvailableStock(Product $product): bool
    {
        $product = $product->fresh();
        if ($product === null || $product->archived_at !== null) {
            return false;
        }
        $location = StockLocation::main();
        if (! $location->active || ! $location->fulfillment_enabled) {
            return false;
        }

        return collect($this->summaries($product->variants()->active()->get(), $location))->contains(fn (array $stock): bool => $stock['available'] > 0);
    }

    private function optionsEligible(ProductVariant $variant): bool
    {
        $variant->loadMissing('values.option');

        return ! $variant->values->contains(fn ($value) => $value->archived_at !== null || ! $value->is_active || $value->option->archived_at !== null);
    }
}
