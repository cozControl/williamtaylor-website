<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;

final class ProductStateFingerprint
{
    public function for(Product $p): string
    {
        return hash('sha256', json_encode(['id' => $p->id, 'lock' => $p->lock_version, 'revision' => $p->current_draft_revision_id, 'default' => $p->default_variant_id, 'archived' => $p->archived_at !== null], JSON_THROW_ON_ERROR));
    }
}
