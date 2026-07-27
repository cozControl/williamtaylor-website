<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SetDefaultProductVariant
{
    public function __construct(private ProductStateFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product, ProductVariant $variant, string $expected): void
    {
        DB::transaction(function () use ($actor, $product, $variant, $expected) {
            $p = Product::lockForUpdate()->findOrFail($product->id);
            $v = ProductVariant::lockForUpdate()->findOrFail($variant->id);
            if (! hash_equals($expected, $this->states->for($p))) {
                throw new StaleCatalogueState;
            }if ($p->archived_at || $v->archived_at || $v->product_id !== $p->id) {
                throw new InvalidArgumentException('Default Variant is invalid.');
            }$required = $p->options()->whereNull('archived_at')->count();
            if ($v->values()->count() !== $required) {
                throw new InvalidArgumentException('Default Variant combination is incomplete.');
            }$before = $p->default_variant_id;
            $p->forceFill(['default_variant_id' => $v->id, 'lock_version' => $p->lock_version + 1])->save();
            $this->audit->handle('product.default-variant.changed', $p, $actor, ['variant_id' => $before], ['variant_id' => $v->id]);
        }, 3);
    }
}
