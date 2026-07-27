<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Catalogue\Support\VariantCombinationFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateProductVariant
{
    public function __construct(private ProductStateFingerprint $states, private VariantCombinationFingerprint $fingerprints, private RecordAuditEvent $audit) {}

    /** @param list<array{option_id: string, value_id: string}> $combination */
    public function handle(User $actor, Product $product, string $expected, array $combination, ?string $sku = null, ?string $barcode = null, ?string $label = null, int $position = 0): ProductVariant
    {
        return DB::transaction(function () use ($actor, $product, $expected, $combination, $sku, $barcode, $label, $position) {
            $p = Product::query()->lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($p))) {
                throw new StaleCatalogueState;
            }if ($p->archived_at) {
                throw new InvalidArgumentException('Archived Products cannot receive Variants.');
            }$fingerprint = $this->fingerprints->for($p, $combination);
            if (ProductVariant::where('product_id', $p->id)->where('combination_fingerprint', $fingerprint)->exists()) {
                throw new InvalidArgumentException('This Product combination already exists.');
            }$normalize = fn (?string $v) => ($x = strtoupper(trim((string) $v))) === '' ? null : $x;
            $variant = ProductVariant::create(['product_id' => $p->id, 'sku' => $normalize($sku), 'barcode' => $normalize($barcode), 'editorial_label' => trim((string) $label) ?: null, 'combination_fingerprint' => $fingerprint, 'catalogue_status' => 'draft', 'position' => $position, 'lock_version' => 0, 'created_by' => $actor->id]);
            foreach ($combination as $pair) {
                DB::table('product_variant_values')->insert(['variant_id' => $variant->id, 'product_option_id' => $pair['option_id'], 'product_option_value_id' => $pair['value_id'], 'created_at' => now('UTC')]);
            }$p->increment('lock_version');
            $this->audit->handle('product.variant.created', $p, $actor, null, ['variant_id' => $variant->id, 'option_count' => count($combination), 'sku_present' => $variant->sku !== null, 'barcode_present' => $variant->barcode !== null]);

            return $variant;
        }, 3);
    }
}
