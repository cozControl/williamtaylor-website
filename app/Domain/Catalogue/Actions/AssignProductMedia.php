<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductMediaAccessibility;
use App\Domain\Catalogue\Support\ProductMediaRoleRegistry;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AssignProductMedia
{
    public function __construct(
        private ProductStateFingerprint $states,
        private ProductMediaRoleRegistry $roles,
        private ProductMediaAccessibility $accessibility,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $product, MediaAsset $asset, string $expected, string $role, ?string $alt = null, ?bool $decorative = null): MediaUsage
    {
        $definition = $this->roles->get(Product::class, $role);

        return DB::transaction(function () use ($actor, $product, $asset, $expected, $role, $alt, $decorative, $definition): MediaUsage {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($lockedProduct))) {
                throw new StaleCatalogueState;
            }
            if ($lockedProduct->archived_at !== null) {
                throw new InvalidArgumentException('Archived products cannot receive media.');
            }

            $lockedAsset = MediaAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $this->accessibility->assertAssignable($lockedAsset, $alt, $decorative);
            $usages = MediaUsage::query()->where('owner_type', Product::class)
                ->where('owner_identifier', $lockedProduct->id)->lockForUpdate()->get();

            if ($usages->contains('media_asset_id', $lockedAsset->id)) {
                throw new InvalidArgumentException('This image is already assigned to the product.');
            }
            $roleUsages = $usages->where('field_role', $role);
            if (($definition['singular'] && $roleUsages->isNotEmpty()) || $roleUsages->count() >= $definition['maximum']) {
                throw new InvalidArgumentException('The product media role has reached its limit.');
            }

            $position = $definition['ordered'] && $roleUsages->isNotEmpty() ? ((int) $roleUsages->max('sort_order') + 1) : 0;
            $usage = MediaUsage::query()->create([
                'id' => (string) Str::ulid(),
                'media_asset_id' => $lockedAsset->id,
                'owner_type' => Product::class,
                'owner_identifier' => $lockedProduct->id,
                'field_role' => $role,
                'locale' => null,
                'alt_text_override' => $alt === null ? null : trim($alt),
                'decorative_override' => false,
                'sort_order' => $position,
            ]);
            $lockedProduct->increment('lock_version');
            $this->audit->handle('product.media.assigned', $lockedProduct, $actor, null, [
                'usage_id' => $usage->id, 'asset_id' => $lockedAsset->id, 'role' => $role,
                'position' => $position, 'has_alt_override' => $alt !== null,
            ]);

            return $usage;
        }, 3);
    }
}
