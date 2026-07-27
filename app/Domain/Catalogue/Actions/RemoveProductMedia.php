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

final class RemoveProductMedia
{
    public function __construct(private ProductStateFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product, MediaUsage $usage, string $expected): void
    {
        DB::transaction(function () use ($actor, $product, $usage, $expected): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($lockedProduct))) {
                throw new StaleCatalogueState;
            }
            $lockedUsage = MediaUsage::query()->lockForUpdate()->findOrFail($usage->id);
            if ($lockedUsage->owner_type !== Product::class || $lockedUsage->owner_identifier !== $lockedProduct->id) {
                throw new InvalidArgumentException('Media usage does not belong to this product.');
            }

            $summary = ['usage_id' => $lockedUsage->id, 'asset_id' => $lockedUsage->media_asset_id, 'role' => $lockedUsage->field_role];
            $role = $lockedUsage->field_role;
            $lockedUsage->delete();
            if ($role === ProductMediaRoleRegistry::GALLERY) {
                $remaining = MediaUsage::query()->where('owner_type', Product::class)
                    ->where('owner_identifier', $lockedProduct->id)->where('field_role', $role)
                    ->orderBy('sort_order')->lockForUpdate()->get();
                foreach ($remaining as $position => $item) {
                    $item->update(['sort_order' => $position]);
                }
            }
            $lockedProduct->forceFill([
                'catalogue_status' => $role === ProductMediaRoleRegistry::PRIMARY ? 'draft' : $lockedProduct->catalogue_status,
                'lock_version' => $lockedProduct->lock_version + 1,
            ])->save();
            $this->audit->handle('product.media.removed', $lockedProduct, $actor, $summary, null);
        }, 3);
    }
}
