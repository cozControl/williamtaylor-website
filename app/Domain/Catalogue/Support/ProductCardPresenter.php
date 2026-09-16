<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Media\Contracts\MediaProvider;

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
        $product->loadMissing(['currentDraftRevision', 'options.values', 'mediaUsages.asset', 'badges']);
        if ($product->archived_at !== null || $product->catalogue_status !== 'ready' || $product->currentDraftRevision === null || $product->base_price_minor === null) {
            return null;
        }

        $usage = $product->mediaUsages->firstWhere('field_role', ProductMediaRoleRegistry::PRIMARY);

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
            'badges' => $product->badges->whereNull('archived_at')->where('badge_key', '!=', 'sold-out')->sortBy('position')->pluck('badge_key')->values()->all(),
            'colours' => $product->options->firstWhere('key', 'colour')?->values
                ->where('is_active', true)
                ->map(fn ($value) => ['label' => $value->label, 'swatch_hex' => $value->swatch_hex])
                ->values()->all() ?? [],
        ];
    }
}
