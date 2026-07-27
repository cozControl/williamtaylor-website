<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UpdateProductOptionValue
{
    public function __construct(
        private ProductStateFingerprint $states,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        Product $product,
        ProductOption $option,
        ProductOptionValue $value,
        string $expected,
        string $key,
        string $label,
        int $position,
    ): ProductOptionValue {
        return DB::transaction(function () use ($actor, $product, $option, $value, $expected, $key, $label, $position): ProductOptionValue {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lockedOption = ProductOption::query()->lockForUpdate()->findOrFail($option->id);
            $lockedValue = ProductOptionValue::query()->lockForUpdate()->findOrFail($value->id);
            if ($lockedOption->product_id !== $lockedProduct->id || $lockedValue->product_option_id !== $lockedOption->id) {
                throw new InvalidArgumentException('Product option/value ownership is invalid.');
            }
            if (! hash_equals($expected, $this->states->for($lockedProduct))) {
                throw new StaleCatalogueState;
            }
            if ($lockedProduct->archived_at !== null || $lockedOption->archived_at !== null || $lockedValue->archived_at !== null) {
                throw new InvalidArgumentException('Archived Product option values are read-only.');
            }

            $key = Str::slug($key, '_');
            if ($key !== $lockedValue->key) {
                throw new InvalidArgumentException('Product option value keys are identity-bearing and immutable.');
            }
            $label = trim($label);
            if ($label === '' || mb_strlen($label) > 100 || $position < 0 || $position > 65535) {
                throw new InvalidArgumentException('Product option value label or position is invalid.');
            }
            if ($lockedOption->values()->where('key', $key)->whereKeyNot($lockedValue->id)->exists()) {
                throw new InvalidArgumentException('Product option value key is already in use.');
            }
            if ($lockedOption->values()->where('position', $position)->whereKeyNot($lockedValue->id)->exists()) {
                throw new InvalidArgumentException('Product option value position is already in use.');
            }
            if ($lockedValue->label === $label && $lockedValue->position === $position) {
                return $lockedValue;
            }

            $before = ['label' => $lockedValue->label, 'position' => $lockedValue->position];
            try {
                $lockedValue->forceFill(['label' => $label, 'position' => $position])->save();
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    throw new InvalidArgumentException('Product option value uniqueness conflict.', previous: $exception);
                }
                throw $exception;
            }
            $lockedProduct->forceFill(['lock_version' => $lockedProduct->lock_version + 1])->save();
            $this->audit->handle(
                'product.option-value.updated',
                $lockedProduct,
                $actor,
                ['option_id' => $lockedOption->id, 'value_id' => $lockedValue->id, ...$before],
                ['option_id' => $lockedOption->id, 'value_id' => $lockedValue->id, 'label' => $label, 'position' => $position],
            );

            return $lockedValue;
        }, 3);
    }
}
