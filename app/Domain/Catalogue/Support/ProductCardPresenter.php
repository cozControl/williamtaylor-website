<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductBadge;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;

final class ProductCardPresenter
{
    public function __construct(private MediaProvider $media, private ProductPrice $prices) {}

    /** @param iterable<Product> $products */
    public function warmAvailability(iterable $products): void
    {
        app(InventoryAvailabilityService::class)->storefront(array_values(collect($products)->map(fn (Product $product): string => $product->id)->all()));
    }

    /** @return array<string, mixed>|null */
    public function present(Product $product): ?array
    {
        $product->loadMissing(['currentDraftRevision', 'options.values']);
        if ($product->archived_at !== null || $product->catalogue_status !== 'ready' || $product->currentDraftRevision === null || $product->base_price_minor === null) {
            return null;
        }

        $usage = MediaUsage::query()->with('asset')->where('owner_type', Product::class)->where('owner_identifier', $product->id)->where('field_role', ProductMediaRoleRegistry::PRIMARY)->first();

        return [
            'is_available' => collect(app(InventoryAvailabilityService::class)->storefront([$product->id])[$product->id])->contains('is_available', true),
            'slug' => $product->slug,
            'url' => route('products.show', $product->slug),
            'title' => $product->currentDraftRevision->title,
            'price' => $this->prices->format($product->base_price_minor, $product->currency),
            'compare_at_price' => $this->prices->format($product->compare_at_price_minor, $product->currency),
            'image' => $usage === null ? null : [
                'url' => $this->media->deliveryUrl($usage->asset->provider_public_id, $usage->asset->resource_type->value, 'product_card', $usage->asset->focal_x !== null ? (float) $usage->asset->focal_x : null, $usage->asset->focal_y !== null ? (float) $usage->asset->focal_y : null),
                'alt' => $usage->alt_text_override ?: $usage->asset->default_alt_text ?: $usage->asset->internal_title,
            ],
            'badges' => ProductBadge::query()->active()->where('product_id', $product->id)->where('badge_key', '!=', 'sold-out')->orderBy('position')->pluck('badge_key')->all(),
            'colours' => $product->options->firstWhere('key', 'colour')?->values
                ->where('is_active', true)
                ->map(fn ($value) => ['label' => $value->label, 'swatch_hex' => $value->swatch_hex])
                ->values()->all() ?? [],
        ];
    }
}
