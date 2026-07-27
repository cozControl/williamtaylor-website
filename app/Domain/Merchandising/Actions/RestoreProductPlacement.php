<?php

namespace App\Domain\Merchandising\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Merchandising\Exceptions\StaleMerchandisingState;
use App\Domain\Merchandising\Models\ProductPlacement;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Domain\Merchandising\Support\ProductMerchandisingEligibilityEvaluator;
use App\Domain\Merchandising\Support\ProductPlacementSetFingerprint;
use App\Domain\Merchandising\Support\ProductPlacementSlotRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RestoreProductPlacement
{
    public function __construct(
        private ProductPlacementSlotRegistry $registry,
        private ProductPlacementSetFingerprint $states,
        private ProductMerchandisingEligibilityEvaluator $eligibility,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, string $slot, ProductPlacement $placement, string $expected): void
    {
        $definition = $this->registry->get($slot);
        DB::transaction(function () use ($actor, $slot, $placement, $expected, $definition): void {
            $item = ProductPlacement::query()->lockForUpdate()->findOrFail($placement->id);
            if ($item->slot_key !== $slot) {
                throw new InvalidArgumentException('Product placement slot mismatch.');
            }
            if (! hash_equals($expected, $this->states->for($slot))) {
                throw new StaleMerchandisingState;
            }
            if ($item->archived_at === null) {
                return;
            }
            $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
            if (! $this->eligibility->evaluate($product)->eligible) {
                throw new InvalidArgumentException('Product is not merchandising eligible.');
            }
            $active = ProductPlacement::query()->active()->where('slot_key', $slot)->lockForUpdate()->get();
            if ($active->contains('product_id', $product->id) || $active->count() >= $definition['maximum']) {
                throw new InvalidArgumentException('Product placement cannot be restored.');
            }
            $position = $active->count();
            $item->update([
                'position' => $position, 'archived_at' => null, 'archived_by' => null, 'archive_reason' => null,
                'active_key' => ActiveOrderingKeys::active($slot, $product->id),
                'position_key' => ActiveOrderingKeys::active($slot, (string) $position),
            ]);
            $this->audit->handle('product.placement.restored', $item, $actor, ['state' => 'archived'], ['state' => 'active', 'position' => $position]);
        }, 3);
    }
}
