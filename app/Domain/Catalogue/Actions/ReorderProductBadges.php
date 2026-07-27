<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductBadge;
use App\Domain\Catalogue\Support\ProductBadgeSetFingerprint;
use App\Domain\Merchandising\Support\ActiveOrderingKeys;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReorderProductBadges
{
    public function __construct(private ProductBadgeSetFingerprint $states, private RecordAuditEvent $audit) {}

    /** @param list<string> $orderedIds */
    public function handle(User $actor, Product $product, string $expected, array $orderedIds): void
    {
        DB::transaction(function () use ($actor, $product, $expected, $orderedIds): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($locked))) {
                throw new StaleCatalogueState;
            }
            $badges = ProductBadge::query()->active()->where('product_id', $locked->id)->orderBy('position')->lockForUpdate()->get();
            if (count($orderedIds) !== count(array_unique($orderedIds)) || $badges->pluck('id')->sort()->values()->all() !== collect($orderedIds)->sort()->values()->all()) {
                throw new InvalidArgumentException('A complete, duplicate-free Product badge order is required.');
            }
            if ($badges->pluck('id')->all() === $orderedIds) {
                return;
            }
            foreach ($orderedIds as $position => $id) {
                ProductBadge::query()->whereKey($id)->update(['position' => 60000 + $position, 'position_key' => ActiveOrderingKeys::active($locked->id, 'temporary', $id)]);
            }
            foreach ($orderedIds as $position => $id) {
                ProductBadge::query()->whereKey($id)->update(['position' => $position, 'position_key' => ActiveOrderingKeys::active($locked->id, (string) $position)]);
            }
            $locked->increment('lock_version');
            $this->audit->handle('product.badges.reordered', $locked, $actor, null, ['badge_ids' => $orderedIds]);
        }, 3);
    }
}
