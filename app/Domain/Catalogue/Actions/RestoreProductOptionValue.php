<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RestoreProductOptionValue
{
    public function __construct(
        private ProductStateFingerprint $states,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $product, ProductOption $option, ProductOptionValue $value, string $expected): void
    {
        DB::transaction(function () use ($actor, $product, $option, $value, $expected): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lockedOption = ProductOption::query()->lockForUpdate()->findOrFail($option->id);
            $lockedValue = ProductOptionValue::query()->lockForUpdate()->findOrFail($value->id);

            if ($lockedOption->product_id !== $lockedProduct->id || $lockedValue->product_option_id !== $lockedOption->id) {
                throw new InvalidArgumentException('Product option/value ownership is invalid.');
            }
            if (! hash_equals($expected, $this->states->for($lockedProduct))) {
                throw new StaleCatalogueState;
            }
            if ($lockedProduct->archived_at || $lockedOption->archived_at) {
                throw new InvalidArgumentException('Product and parent option must be active.');
            }
            if ($lockedValue->archived_at === null) {
                return;
            }
            if ($lockedOption->values()->whereNull('archived_at')->where('key', $lockedValue->key)->whereKeyNot($lockedValue->id)->exists()) {
                throw new InvalidArgumentException('An active option value already uses this key.');
            }

            $lockedValue->forceFill(['archived_at' => null])->save();
            $lockedProduct->forceFill([
                'catalogue_status' => 'draft',
                'lock_version' => $lockedProduct->lock_version + 1,
            ])->save();
            $this->audit->handle(
                'product.option-value.restored',
                $lockedProduct,
                $actor,
                null,
                ['option_id' => $lockedOption->id, 'value_id' => $lockedValue->id],
            );
        }, 3);
    }
}
