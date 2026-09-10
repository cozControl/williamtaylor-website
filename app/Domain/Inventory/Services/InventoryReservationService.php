<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Checkout\Models\OrderLine;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Inventory\Models\StockLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class InventoryReservationService
{
    public function activeQuantity(string $variantId, string $locationId): int
    {
        return (int) InventoryReservation::query()->where('variant_id', $variantId)->where('stock_location_id', $locationId)->where('status', ReservationStatus::Active)->sum('quantity');
    }

    public function reserve(OrderLine $line, StockLocation $location): InventoryReservation
    {
        return $this->reserveMany([$line], $location)[0];
    }

    /** @param list<OrderLine> $lines
     * @return list<InventoryReservation>
     */
    public function reserveMany(array $lines, StockLocation $location): array
    {
        return DB::transaction(function () use ($lines, $location) {
            // Same parent order as ledger writes. Checkout already owns these locks.
            Product::query()->whereIn('id', array_map(fn ($line) => $line->product_id, $lines))->orderBy('id')->lockForUpdate()->get();
            $variants = ProductVariant::query()->whereIn('id', array_map(fn ($line) => $line->variant_id, $lines))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $location = StockLocation::query()->sharedLock()->findOrFail($location->id);
            $existingLines = InventoryReservation::query()->whereIn('order_line_id', array_map(fn ($line) => $line->id, $lines))->lockForUpdate()->get()->keyBy('order_line_id');
            $stock = app(InventoryAvailabilityService::class)->summaries($variants, $location);
            $result = [];
            foreach ($lines as $line) {
                $variant = $variants->get($line->variant_id);
                $existing = $existingLines->get($line->id);
                if ($existing) {
                    if ($existing->order_id !== $line->order_id || $existing->variant_id !== $line->variant_id || $existing->stock_location_id !== $location->id || $existing->quantity !== $line->quantity || $existing->status !== ReservationStatus::Active) {
                        throw ValidationException::withMessages(['cart' => 'This order submission has already been used.']);
                    }

                    $result[] = $existing;

                    continue;
                }
                if (! $line->exists || ! $variant || $variant->product_id !== $line->product_id || $line->quantity < 1 || ($stock[$line->variant_id]['available'] ?? 0) < $line->quantity) {
                    throw ValidationException::withMessages(['cart' => 'One of the items in your bag is no longer available in the requested quantity.']);
                }

                $result[] = InventoryReservation::query()->create(['order_id' => $line->order_id, 'order_line_id' => $line->id, 'variant_id' => $line->variant_id, 'stock_location_id' => $location->id, 'quantity' => $line->quantity, 'status' => ReservationStatus::Active, 'reserved_at' => now('UTC')]);
                $stock[$line->variant_id]['available'] -= $line->quantity;
            }

            return $result;
        });
    }
}
