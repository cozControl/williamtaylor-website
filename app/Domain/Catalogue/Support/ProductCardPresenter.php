<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductBadge;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;

final class ProductCardPresenter
{
    public function __construct(private MediaProvider $media, private ProductPrice $prices) {}

    /** @return array<string, mixed>|null */
    public function present(Product $product): ?array
    {
        $product->loadMissing('currentDraftRevision');
        if ($product->archived_at !== null || $product->catalogue_status !== 'ready' || $product->currentDraftRevision === null || $product->base_price_minor === null) {
            return null;
        }

        $usage = MediaUsage::query()->with('asset')->where('owner_type', Product::class)->where('owner_identifier', $product->id)->where('field_role', ProductMediaRoleRegistry::PRIMARY)->first();

        return [
            'slug' => $product->slug,
            'title' => $product->currentDraftRevision->title,
            'price' => $this->prices->format($product->base_price_minor, $product->currency),
            'compare_at_price' => $this->prices->format($product->compare_at_price_minor, $product->currency),
            'image' => $usage === null ? null : [
                'url' => $this->media->deliveryUrl($usage->asset->provider_public_id, $usage->asset->resource_type->value, 'product_card', $usage->asset->focal_x !== null ? (float) $usage->asset->focal_x : null, $usage->asset->focal_y !== null ? (float) $usage->asset->focal_y : null),
                'alt' => $usage->alt_text_override ?: $usage->asset->default_alt_text ?: $usage->asset->internal_title,
            ],
            'badges' => ProductBadge::query()->active()->where('product_id', $product->id)->orderBy('position')->pluck('badge_key')->all(),
        ];
    }
}
