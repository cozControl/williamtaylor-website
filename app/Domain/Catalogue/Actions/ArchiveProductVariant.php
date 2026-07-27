<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Catalogue\Support\VariantStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArchiveProductVariant
{
    public function __construct(
        private ProductStateFingerprint $productStates,
        private VariantStateFingerprint $variantStates,
        private CatalogueReadinessEvaluator $readiness,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, Product $product, ProductVariant $variant, string $expectedProduct, string $expectedVariant, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);

        DB::transaction(function () use ($actor, $product, $variant, $expectedProduct, $expectedVariant, $reason): void {
            $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);
            $lockedVariant = ProductVariant::query()->lockForUpdate()->findOrFail($variant->id);
            $this->validate($lockedProduct, $lockedVariant, $expectedProduct, $expectedVariant);

            if ($lockedVariant->archived_at !== null) {
                return;
            }

            $wasDefault = $lockedProduct->default_variant_id === $lockedVariant->id;
            $lockedVariant->forceFill([
                'archived_at' => now('UTC'),
                'archived_by' => $actor->id,
                'archive_reason' => $reason,
                'lock_version' => $lockedVariant->lock_version + 1,
            ])->save();

            if ($wasDefault) {
                $lockedProduct->default_variant_id = null;
            }
            if ($lockedProduct->catalogue_status === 'ready' && ! $this->readiness->evaluate($lockedProduct)->ready) {
                $lockedProduct->catalogue_status = 'draft';
            }
            $lockedProduct->lock_version++;
            $lockedProduct->save();

            $this->audit->handle(
                'product.variant.archived',
                $lockedProduct,
                $actor,
                ['default' => $wasDefault],
                ['variant_id' => $lockedVariant->id, 'default_cleared' => $wasDefault, 'status' => $lockedProduct->catalogue_status],
                reason: $reason,
            );
        }, 3);
    }

    private function validate(Product $product, ProductVariant $variant, string $expectedProduct, string $expectedVariant): void
    {
        if ($variant->product_id !== $product->id) {
            throw new InvalidArgumentException('Variant does not belong to Product.');
        }
        if (! hash_equals($expectedProduct, $this->productStates->for($product))
            || ! hash_equals($expectedVariant, $this->variantStates->for($product, $variant))) {
            throw new StaleCatalogueState;
        }
        if ($product->archived_at) {
            throw new InvalidArgumentException('Archived Products are read-only.');
        }
    }
}
