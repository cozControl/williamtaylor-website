<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductMediaRoleRegistry;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReorderProductGallery
{
    public function __construct(private ProductStateFingerprint $states, private RecordAuditEvent $audit) {}

    /** @param list<string> $orderedUsageIds */
    public function handle(User $actor, Product $product, string $expected, array $orderedUsageIds): void
    {
        DB::transaction(function () use ($actor, $product, $expected, $orderedUsageIds): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($lockedProduct))) {
                throw new StaleCatalogueState;
            }
            $usages = MediaUsage::query()->where('owner_type', Product::class)
                ->where('owner_identifier', $lockedProduct->id)
                ->where('field_role', ProductMediaRoleRegistry::GALLERY)
                ->orderBy('sort_order')->lockForUpdate()->get();

            if (count($orderedUsageIds) !== count(array_unique($orderedUsageIds))
                || $usages->pluck('id')->sort()->values()->all() !== collect($orderedUsageIds)->sort()->values()->all()) {
                throw new InvalidArgumentException('A complete, duplicate-free gallery order is required.');
            }
            if ($usages->pluck('id')->values()->all() === $orderedUsageIds) {
                return;
            }
            foreach ($orderedUsageIds as $position => $id) {
                MediaUsage::query()->whereKey($id)->update(['sort_order' => 100000 + $position]);
            }
            foreach ($orderedUsageIds as $position => $id) {
                MediaUsage::query()->whereKey($id)->update(['sort_order' => $position]);
            }
            $lockedProduct->increment('lock_version');
            $this->audit->handle('product.media.gallery-reordered', $lockedProduct, $actor, null, ['usage_ids' => $orderedUsageIds]);
        }, 3);
    }
}
