<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;

final class VariantStateFingerprint
{
    public function for(Product $p, ProductVariant $v): string
    {
        return hash('sha256', json_encode(['product_id' => $p->id, 'product_lock' => $p->lock_version, 'variant_id' => $v->id, 'variant_lock' => $v->lock_version, 'archived' => $v->archived_at !== null, 'combination' => $v->combination_fingerprint, 'default' => $p->default_variant_id === $v->id], JSON_THROW_ON_ERROR));
    }
}
