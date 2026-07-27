<?php

namespace App\Domain\Merchandising\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Merchandising\Exceptions\StaleMerchandisingState;
use App\Domain\Merchandising\Models\ProductRelation;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Domain\Merchandising\Support\ProductMerchandisingEligibilityEvaluator;
use App\Domain\Merchandising\Support\ProductRelationKindRegistry;
use App\Domain\Merchandising\Support\ProductRelationSetFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RestoreProductRelation
{
    public function __construct(
        private ProductRelationKindRegistry $registry,
        private ProductRelationSetFingerprint $states,
        private ProductMerchandisingEligibilityEvaluator $eligibility,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $source, ProductRelation $relation, string $expected): void
    {
        DB::transaction(function () use ($actor, $source, $relation, $expected): void {
            $lockedSource = Product::query()->lockForUpdate()->findOrFail($source->id);
            $item = ProductRelation::query()->lockForUpdate()->findOrFail($relation->id);
            if ($item->source_product_id !== $lockedSource->id) {
                throw new InvalidArgumentException('Product relation ownership mismatch.');
            }
            if (! hash_equals($expected, $this->states->for($lockedSource, $item->relation_kind))) {
                throw new StaleMerchandisingState;
            }
            if ($item->archived_at === null) {
                return;
            }
            $definition = $this->registry->get($item->relation_kind);
            $target = Product::query()->lockForUpdate()->findOrFail($item->target_product_id);
            if (! $this->eligibility->evaluate($lockedSource)->eligible || ! $this->eligibility->evaluate($target)->eligible) {
                throw new InvalidArgumentException('Both Products must be merchandising eligible.');
            }
            $active = ProductRelation::query()->active()->where('source_product_id', $lockedSource->id)
                ->where('relation_kind', $item->relation_kind)->lockForUpdate()->get();
            if ($active->contains('target_product_id', $target->id) || $active->count() >= $definition['maximum']) {
                throw new InvalidArgumentException('Product relation cannot be restored.');
            }
            $position = $active->count();
            $item->update([
                'position' => $position, 'archived_at' => null, 'archived_by' => null, 'archive_reason' => null,
                'active_key' => ActiveOrderingKeys::active($lockedSource->id, $item->relation_kind, $target->id),
                'position_key' => ActiveOrderingKeys::active($lockedSource->id, $item->relation_kind, (string) $position),
            ]);
            $this->audit->handle('product.relation.restored', $item, $actor, ['state' => 'archived'], ['state' => 'active', 'position' => $position]);
        }, 3);
    }
}
