<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ClearDefaultProductVariant
{
    public function __construct(private ProductStateFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product, string $expected): void
    {
        DB::transaction(function () use ($actor, $product, $expected) {
            $p = Product::lockForUpdate()->findOrFail($product->id);
            if (! hash_equals($expected, $this->states->for($p))) {
                throw new StaleCatalogueState;
            }if ($p->default_variant_id === null) {
                return;
            }$before = $p->default_variant_id;
            $p->forceFill(['default_variant_id' => null, 'lock_version' => $p->lock_version + 1])->save();
            $this->audit->handle('product.default-variant.changed', $p, $actor, ['variant_id' => $before], ['variant_id' => null]);
        }, 3);
    }
}
