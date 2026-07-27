<?php

namespace App\Domain\Merchandising\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Merchandising\Exceptions\StaleMerchandisingState;
use App\Domain\Merchandising\Models\ProductPlacement;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Domain\Merchandising\Support\ProductPlacementSetFingerprint;
use App\Domain\Merchandising\Support\ProductPlacementSlotRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReorderProductPlacements
{
    public function __construct(private ProductPlacementSlotRegistry $registry, private ProductPlacementSetFingerprint $states, private RecordAuditEvent $audit) {}

    /** @param list<string> $orderedIds */
    public function handle(User $actor, string $slot, string $expected, array $orderedIds): void
    {
        $this->registry->get($slot);
        DB::transaction(function () use ($actor, $slot, $expected, $orderedIds): void {
            $placements = ProductPlacement::query()->active()->where('slot_key', $slot)->orderBy('position')->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->for($slot))) {
                throw new StaleMerchandisingState;
            }
            if (count($orderedIds) !== count(array_unique($orderedIds)) || $placements->pluck('id')->sort()->values()->all() !== collect($orderedIds)->sort()->values()->all()) {
                throw new InvalidArgumentException('A complete, duplicate-free Product placement order is required.');
            }
            if ($placements->pluck('id')->all() === $orderedIds) {
                return;
            }
            foreach ($orderedIds as $position => $id) {
                ProductPlacement::query()->whereKey($id)->update(['position' => 60000 + $position, 'position_key' => ActiveOrderingKeys::active($slot, 'temporary', $id)]);
            }
            foreach ($orderedIds as $position => $id) {
                ProductPlacement::query()->whereKey($id)->update(['position' => $position, 'position_key' => ActiveOrderingKeys::active($slot, (string) $position)]);
            }
            $this->audit->handle('product.placements.reordered', $placements->first(), $actor, null, ['slot_key' => $slot, 'placement_ids' => $orderedIds]);
        }, 3);
    }
}
