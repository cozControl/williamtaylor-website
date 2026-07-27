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
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AddProductRelation
{
    public function __construct(
        private ProductRelationKindRegistry $registry,
        private ProductRelationSetFingerprint $states,
        private ProductMerchandisingEligibilityEvaluator $eligibility,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $source, Product $target, string $kind, string $expected): ProductRelation
    {
        $definition = $this->registry->get($kind);
        if ($source->id === $target->id) {
            throw new InvalidArgumentException('A Product cannot relate to itself.');
        }

        return DB::transaction(function () use ($actor, $source, $target, $kind, $expected, $definition): ProductRelation {
            $products = Product::query()->whereIn('id', [$source->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lockedSource = $products->get($source->id);
            $lockedTarget = $products->get($target->id);
            if (! $lockedSource || ! $lockedTarget) {
                throw new InvalidArgumentException('Source and target Products must exist.');
            }
            if (! hash_equals($expected, $this->states->for($lockedSource, $kind))) {
                throw new StaleMerchandisingState;
            }
            if (! $this->eligibility->evaluate($lockedSource)->eligible || ! $this->eligibility->evaluate($lockedTarget)->eligible) {
                throw new InvalidArgumentException('Both Products must be merchandising eligible.');
            }
            $active = ProductRelation::query()->active()->where('source_product_id', $lockedSource->id)
                ->where('relation_kind', $kind)->lockForUpdate()->get();
            if ($active->contains('target_product_id', $lockedTarget->id)) {
                throw new InvalidArgumentException('This Product relation already exists.');
            }
            if ($active->count() >= $definition['maximum']) {
                throw new InvalidArgumentException('The Product relation limit has been reached.');
            }
            $id = (string) Str::ulid();
            $position = $active->count();
            $relation = ProductRelation::query()->create([
                'id' => $id, 'source_product_id' => $lockedSource->id, 'target_product_id' => $lockedTarget->id,
                'relation_kind' => $kind, 'position' => $position,
                'active_key' => ActiveOrderingKeys::active($lockedSource->id, $kind, $lockedTarget->id),
                'position_key' => ActiveOrderingKeys::active($lockedSource->id, $kind, (string) $position),
            ]);
            $this->audit->handle('product.relation.added', $relation, $actor, null, [
                'source_product_id' => $lockedSource->id, 'target_product_id' => $lockedTarget->id,
                'relation_kind' => $kind, 'position' => $position,
            ]);

            return $relation;
        }, 3);
    }
}
