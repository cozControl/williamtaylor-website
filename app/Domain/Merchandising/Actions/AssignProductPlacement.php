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
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AssignProductPlacement
{
    public function __construct(
        private ProductPlacementSlotRegistry $registry,
        private ProductPlacementSetFingerprint $states,
        private ProductMerchandisingEligibilityEvaluator $eligibility,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, string $slot, Product $product, string $expected): ProductPlacement
    {
        $definition = $this->registry->get($slot);
        if ($definition['target_type'] !== 'product') {
            throw new InvalidArgumentException('Only Product placement targets are supported.');
        }

        return DB::transaction(function () use ($actor, $slot, $product, $expected, $definition): ProductPlacement {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $active = ProductPlacement::query()->active()->where('slot_key', $slot)->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->for($slot))) {
                throw new StaleMerchandisingState;
            }
            if (! $this->eligibility->evaluate($lockedProduct)->eligible) {
                throw new InvalidArgumentException('Product is not merchandising eligible.');
            }
            if ($active->contains('product_id', $lockedProduct->id)) {
                throw new InvalidArgumentException('Product is already assigned to this slot.');
            }
            if ($active->count() >= $definition['maximum']) {
                throw new InvalidArgumentException('The Product placement slot is full.');
            }
            $id = (string) Str::ulid();
            $position = $active->count();
            $placement = ProductPlacement::query()->create([
                'id' => $id, 'slot_key' => $slot, 'product_id' => $lockedProduct->id, 'position' => $position,
                'active_key' => ActiveOrderingKeys::active($slot, $lockedProduct->id),
                'position_key' => ActiveOrderingKeys::active($slot, (string) $position),
            ]);
            $this->audit->handle('product.placement.assigned', $placement, $actor, null, [
                'slot_key' => $slot, 'product_id' => $lockedProduct->id, 'position' => $position,
            ]);

            return $placement;
        }, 3);
    }
}
