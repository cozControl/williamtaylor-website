<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Catalogue\Support\VariantCombinationFingerprint;
use App\Domain\Catalogue\Support\VariantStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RestoreProductVariant
{
    public function __construct(
        private ProductStateFingerprint $productStates,
        private VariantStateFingerprint $variantStates,
        private VariantCombinationFingerprint $fingerprints,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $product, ProductVariant $variant, string $expectedProduct, string $expectedVariant): void
    {
        DB::transaction(function () use ($actor, $product, $variant, $expectedProduct, $expectedVariant): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lockedVariant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);

            if ($lockedVariant->product_id !== $lockedProduct->id) {
                throw new InvalidArgumentException('Variant does not belong to Product.');
            }
            if (! hash_equals($expectedProduct, $this->productStates->for($lockedProduct))
                || ! hash_equals($expectedVariant, $this->variantStates->for($lockedProduct, $lockedVariant))) {
                throw new StaleCatalogueState;
            }
            if ($lockedProduct->archived_at) {
                throw new InvalidArgumentException('Archived Products are read-only.');
            }
            if ($lockedVariant->archived_at === null) {
                return;
            }

            $combination = array_values(DB::table('product_variant_values')
                ->where('variant_id', $lockedVariant->id)
                ->orderBy('product_option_id')
                ->get()
                ->map(function (object $row): array {
                    if (! is_string($row->product_option_id) || ! is_string($row->product_option_value_id)) {
                        throw new InvalidArgumentException('Variant relationship identifiers are invalid.');
                    }

                    return [
                        'option_id' => $row->product_option_id,
                        'value_id' => $row->product_option_value_id,
                    ];
                })->all());
            $fingerprint = $this->fingerprints->for($lockedProduct, $combination);

            if (ProductVariant::query()
                ->active()
                ->where('product_id', $lockedProduct->id)
                ->where('combination_fingerprint', $fingerprint)
                ->whereKeyNot($lockedVariant->id)
                ->exists()) {
                throw new InvalidArgumentException('An active Variant already uses this combination.');
            }

            $lockedVariant->forceFill([
                'combination_fingerprint' => $fingerprint,
                'archived_at' => null,
                'archived_by' => null,
                'archive_reason' => null,
                'lock_version' => $lockedVariant->lock_version + 1,
            ])->save();
            $lockedProduct->forceFill([
                'catalogue_status' => 'draft',
                'lock_version' => $lockedProduct->lock_version + 1,
            ])->save();

            $this->audit->handle(
                'product.variant.restored',
                $lockedProduct,
                $actor,
                ['state' => 'archived'],
                ['variant_id' => $lockedVariant->id, 'state' => 'active', 'status' => 'draft'],
            );
        }, 3);
    }
}
