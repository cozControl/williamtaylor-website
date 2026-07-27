<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Catalogue\Support\ProductTypeRegistry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class UpdateProductOption
{
    public function __construct(
        private ProductStateFingerprint $states,
        private ProductTypeRegistry $types,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        Product $product,
        ProductOption $option,
        string $expected,
        string $key,
        string $label,
        int $position,
    ): ProductOption {
        return DB::transaction(function () use ($actor, $product, $option, $expected, $key, $label, $position): ProductOption {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lockedOption = ProductOption::query()->lockForUpdate()->findOrFail($option->id);
            if ($lockedOption->product_id !== $lockedProduct->id) {
                throw new InvalidArgumentException('Option does not belong to Product.');
            }
            if (! hash_equals($expected, $this->states->for($lockedProduct))) {
                throw new StaleCatalogueState;
            }
            if ($lockedProduct->archived_at !== null || $lockedOption->archived_at !== null) {
                throw new InvalidArgumentException('Archived Products and options are read-only.');
            }

            $definition = $this->types->get($lockedProduct->product_type);
            $key = Str::slug($key, '_');
            if (! in_array($key, $definition['option_keys'], true)) {
                throw new InvalidArgumentException('Unsupported Product option.');
            }
            if ($key !== $lockedOption->key) {
                throw new InvalidArgumentException('Product option keys are identity-bearing and immutable.');
            }
            if ($lockedProduct->options()->whereNull('archived_at')->count() > $definition['maximum_options']) {
                throw new InvalidArgumentException('Maximum active Product options exceeded.');
            }

            $label = trim($label);
            if ($label === '' || mb_strlen($label) > 80 || $position < 0 || $position > 255) {
                throw new InvalidArgumentException('Product option label or position is invalid.');
            }
            if ($lockedProduct->options()->where('position', $position)->whereKeyNot($lockedOption->id)->exists()) {
                throw new InvalidArgumentException('Product option position is already in use.');
            }
            if ($lockedOption->label === $label && $lockedOption->position === $position) {
                return $lockedOption;
            }

            $before = ['label' => $lockedOption->label, 'position' => $lockedOption->position];
            try {
                $lockedOption->forceFill(['label' => $label, 'position' => $position])->save();
            } catch (QueryException $exception) {
                if ((string) $exception->getCode() === '23000') {
                    throw new InvalidArgumentException('Product option position is already in use.', previous: $exception);
                }
                throw $exception;
            }
            $lockedProduct->forceFill(['lock_version' => $lockedProduct->lock_version + 1])->save();
            $this->audit->handle(
                'product.option.updated',
                $lockedProduct,
                $actor,
                ['option_id' => $lockedOption->id, ...$before],
                ['option_id' => $lockedOption->id, 'label' => $label, 'position' => $position],
            );

            return $lockedOption;
        }, 3);
    }
}
