<?php

namespace App\Domain\Merchandising\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Merchandising\Exceptions\StaleMerchandisingState;
use App\Domain\Merchandising\Models\ProductRelation;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Domain\Merchandising\Support\ProductRelationKindRegistry;
use App\Domain\Merchandising\Support\ProductRelationSetFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReorderProductRelations
{
    public function __construct(private ProductRelationKindRegistry $registry, private ProductRelationSetFingerprint $states, private RecordAuditEvent $audit) {}

    /** @param list<string> $orderedIds */
    public function handle(User $actor, Product $source, string $kind, string $expected, array $orderedIds): void
    {
        $this->registry->get($kind);
        DB::transaction(function () use ($actor, $source, $kind, $expected, $orderedIds): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($source->id);
            if (! hash_equals($expected, $this->states->for($locked, $kind))) {
                throw new StaleMerchandisingState;
            }
            $relations = ProductRelation::query()->active()->where('source_product_id', $locked->id)
                ->where('relation_kind', $kind)->orderBy('position')->lockForUpdate()->get();
            if (count($orderedIds) !== count(array_unique($orderedIds)) || $relations->pluck('id')->sort()->values()->all() !== collect($orderedIds)->sort()->values()->all()) {
                throw new InvalidArgumentException('A complete, duplicate-free Product relation order is required.');
            }
            if ($relations->pluck('id')->all() === $orderedIds) {
                return;
            }
            foreach ($orderedIds as $position => $id) {
                ProductRelation::query()->whereKey($id)->update(['position' => 60000 + $position, 'position_key' => ActiveOrderingKeys::active($locked->id, $kind, 'temporary', $id)]);
            }
            foreach ($orderedIds as $position => $id) {
                ProductRelation::query()->whereKey($id)->update(['position' => $position, 'position_key' => ActiveOrderingKeys::active($locked->id, $kind, (string) $position)]);
            }
            $this->audit->handle('product.relations.reordered', $locked, $actor, null, ['relation_kind' => $kind, 'relation_ids' => $orderedIds]);
        }, 3);
    }
}
