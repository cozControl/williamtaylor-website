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

final class UpdateProductVariant
{
    public function __construct(
        private ProductStateFingerprint $productStates,
        private VariantStateFingerprint $variantStates,
        private VariantCombinationFingerprint $fingerprints,
        private RecordAuditEvent $audit,
    ) {}

    /** @param list<array{option_id: string, value_id: string}>|null $combination */
    public function handle(
        User $actor,
        Product $product,
        ProductVariant $variant,
        string $expectedProduct,
        string $expectedVariant,
        ?string $sku,
        ?string $barcode,
        ?string $label,
        int $position,
        ?array $combination = null,
        ?int $priceOverrideMinor = null,
        ?int $compareAtPriceOverrideMinor = null,
    ): ProductVariant {
        return DB::transaction(function () use ($actor, $product, $variant, $expectedProduct, $expectedVariant, $sku, $barcode, $label, $position, $combination, $priceOverrideMinor, $compareAtPriceOverrideMinor): ProductVariant {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lockedVariant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);

            if ($lockedVariant->product_id !== $lockedProduct->id) {
                throw new InvalidArgumentException('Variant does not belong to Product.');
            }

            if (! hash_equals($expectedProduct, $this->productStates->for($lockedProduct))
                || ! hash_equals($expectedVariant, $this->variantStates->for($lockedProduct, $lockedVariant))) {
                throw new StaleCatalogueState;
            }

            if ($lockedProduct->archived_at || $lockedVariant->archived_at) {
                throw new InvalidArgumentException('Archived Products and Variants are read-only.');
            }

            $normalize = fn (?string $value): ?string => ($normalized = strtoupper(trim((string) $value))) === '' ? null : $normalized;
            $next = [
                'sku' => $normalize($sku),
                'barcode' => $normalize($barcode),
                'editorial_label' => trim((string) $label) ?: null,
                'position' => $position,
                'combination_fingerprint' => $lockedVariant->combination_fingerprint,
                'price_override_minor' => $priceOverrideMinor,
                'compare_at_price_override_minor' => $compareAtPriceOverrideMinor,
            ];

            if ($combination !== null) {
                $next['combination_fingerprint'] = $this->fingerprints->for($lockedProduct, $combination);
            }

            foreach (['sku', 'barcode'] as $field) {
                if ($next[$field] !== null && ProductVariant::query()->where($field, $next[$field])->whereKeyNot($lockedVariant->id)->exists()) {
                    throw new InvalidArgumentException("Variant {$field} is already in use.");
                }
            }

            if (ProductVariant::query()
                ->where('product_id', $lockedProduct->id)
                ->where('combination_fingerprint', $next['combination_fingerprint'])
                ->whereKeyNot($lockedVariant->id)
                ->exists()) {
                throw new InvalidArgumentException('This Product combination already exists.');
            }

            $changed = array_keys(array_filter(
                $next,
                fn (mixed $value, string $field): bool => $lockedVariant->getAttribute($field) !== $value,
                ARRAY_FILTER_USE_BOTH,
            ));

            if ($changed === []) {
                return $lockedVariant;
            }

            $lockedVariant->forceFill([...$next, 'lock_version' => $lockedVariant->lock_version + 1])->save();

            if ($combination !== null && in_array('combination_fingerprint', $changed, true)) {
                DB::table('product_variant_values')->where('variant_id', $lockedVariant->id)->delete();
                foreach ($combination as $pair) {
                    DB::table('product_variant_values')->insert([
                        'variant_id' => $lockedVariant->id,
                        'product_option_id' => $pair['option_id'],
                        'product_option_value_id' => $pair['value_id'],
                        'created_at' => now('UTC'),
                    ]);
                }
            }

            $lockedProduct->forceFill(['lock_version' => $lockedProduct->lock_version + 1])->save();
            $this->audit->handle(
                'product.variant.updated',
                $lockedProduct,
                $actor,
                null,
                ['variant_id' => $lockedVariant->id, 'changed_fields' => $changed],
            );

            return $lockedVariant;
        }, 3);
    }
}
