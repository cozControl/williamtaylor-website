<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Catalogue\Support\ProductTypeRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RestoreProductOption
{
    public function __construct(
        private ProductStateFingerprint $states,
        private ProductTypeRegistry $types,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $product, ProductOption $option, string $expected): void
    {
        DB::transaction(function () use ($actor, $product, $option, $expected): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lockedOption = ProductOption::query()->lockForUpdate()->findOrFail($option->id);

            if ($lockedOption->product_id !== $lockedProduct->id) {
                throw new InvalidArgumentException('Option does not belong to Product.');
            }
            if (! hash_equals($expected, $this->states->for($lockedProduct))) {
                throw new StaleCatalogueState;
            }
            if ($lockedProduct->archived_at) {
                throw new InvalidArgumentException('Archived Products are read-only.');
            }
            if ($lockedOption->archived_at === null) {
                return;
            }

            $definition = $this->types->get($lockedProduct->product_type);
            if (! in_array($lockedOption->key, $definition['option_keys'], true)) {
                throw new InvalidArgumentException('Unsupported Product option.');
            }
            if ($lockedProduct->options()->whereNull('archived_at')->count() >= $definition['maximum_options']) {
                throw new InvalidArgumentException('Maximum active Product options reached.');
            }
            if ($lockedProduct->options()->whereNull('archived_at')->where('key', $lockedOption->key)->whereKeyNot($lockedOption->id)->exists()) {
                throw new InvalidArgumentException('An active option already uses this key.');
            }

            $lockedOption->forceFill(['archived_at' => null])->save();
            $lockedProduct->forceFill([
                'catalogue_status' => 'draft',
                'lock_version' => $lockedProduct->lock_version + 1,
            ])->save();
            $this->audit->handle('product.option.restored', $lockedProduct, $actor, null, ['option_id' => $lockedOption->id]);
        }, 3);
    }
}
