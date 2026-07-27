<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArchiveProductOptionValue
{
    public function __construct(private ProductStateFingerprint $states, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Product $product, ProductOption $option, ProductOptionValue $value, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $product, $option, $value, $expected, $reason): void {
            $p = Product::query()->lockForUpdate()->findOrFail($product->id);
            $o = ProductOption::query()->lockForUpdate()->findOrFail($option->id);
            $v = ProductOptionValue::query()->lockForUpdate()->findOrFail($value->id);
            if ($o->product_id !== $p->id || $v->product_option_id !== $o->id) {
                throw new InvalidArgumentException('Product option/value ownership is invalid.');
            }
            if (! hash_equals($expected, $this->states->for($p))) {
                throw new StaleCatalogueState;
            }
            if ($p->archived_at || $o->archived_at) {
                throw new InvalidArgumentException('Product and parent option must be active.');
            }
            if ($v->archived_at !== null) {
                return;
            }
            $v->forceFill(['archived_at' => now('UTC')])->save();
            $p->forceFill(['catalogue_status' => 'draft', 'lock_version' => $p->lock_version + 1])->save();
            $this->audit->handle('product.option-value.archived', $p, $actor, null, ['option_id' => $o->id, 'value_id' => $v->id], reason: $reason);
        }, 3);
    }
}
