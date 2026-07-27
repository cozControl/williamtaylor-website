<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductMediaAccessibility;
use App\Domain\Catalogue\Support\ProductMediaRoleRegistry;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateProductMediaUsage
{
    public function __construct(
        private ProductStateFingerprint $states,
        private ProductMediaRoleRegistry $roles,
        private ProductMediaAccessibility $accessibility,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $product, MediaUsage $usage, string $expected, ?string $alt = null, ?bool $decorative = null): MediaUsage
    {
        return DB::transaction(function () use ($actor, $product, $usage, $expected, $alt, $decorative): MediaUsage {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($lockedProduct))) {
                throw new StaleCatalogueState;
            }
            $lockedUsage = MediaUsage::query()->with('asset')->lockForUpdate()->findOrFail($usage->id);
            if ($lockedUsage->owner_type !== Product::class || $lockedUsage->owner_identifier !== $lockedProduct->id) {
                throw new InvalidArgumentException('Media usage does not belong to this product.');
            }
            $this->roles->get(Product::class, $lockedUsage->field_role);
            $this->accessibility->assertAssignable($lockedUsage->asset, $alt, $decorative);

            $nextAlt = $alt === null ? null : trim($alt);
            if ($lockedUsage->alt_text_override === $nextAlt && $lockedUsage->decorative_override === false) {
                return $lockedUsage;
            }
            $before = ['has_alt_override' => $lockedUsage->alt_text_override !== null];
            $lockedUsage->forceFill(['alt_text_override' => $nextAlt, 'decorative_override' => false])->save();
            $lockedProduct->increment('lock_version');
            $this->audit->handle('product.media.updated', $lockedProduct, $actor, $before, [
                'usage_id' => $lockedUsage->id, 'has_alt_override' => $nextAlt !== null,
            ]);

            return $lockedUsage;
        }, 3);
    }
}
