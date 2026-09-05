<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductBadge;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Models\ProductOption;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Merchandising\Models\ProductRelation;

final class ProductPresenter
{
    public function __construct(private MediaProvider $media, private ProductPrice $prices, private ProductCardPresenter $cards, private CatalogueReadinessEvaluator $readiness) {}

    /** @return array<string, mixed>|null */
    public function resolve(string $slug): ?array
    {
        $product = Product::query()->where('slug', $slug)->whereNull('archived_at')->where('catalogue_status', 'ready')
            ->with(['currentDraftRevision', 'categories', 'options' => fn ($query) => $query->active()->with(['values' => fn ($values) => $values->active()->where('is_active', true)]), 'variants' => fn ($query) => $query->active()->with('values'), 'defaultVariant'])->first();
        if ($product === null || ! $this->readiness->evaluate($product)->ready) {
            return null;
        }

        $images = $this->images(Product::class, $product->id, [ProductMediaRoleRegistry::PRIMARY, ProductMediaRoleRegistry::GALLERY]);
        $colourOption = $product->options->firstWhere('key', 'colour');
        $colours = $colourOption === null ? collect() : $colourOption->values;
        $colourImages = $colours->mapWithKeys(fn (ProductOptionValue $value) => [$value->id => $this->images(ProductOptionValue::class, $value->id, ['colour_primary', 'colour_gallery'])])->all();
        $primaryCategory = $product->categories->first(fn (ProductCategory $category): bool => (bool) $category->getRelation('pivot')->getAttribute('is_primary')) ?? $product->categories->first();
        $related = ProductRelation::query()->active()->where('source_product_id', $product->id)->where('relation_kind', 'related')->with('target.currentDraftRevision')->orderBy('position')->limit(4)->get();

        return [
            'id' => $product->id, 'slug' => $product->slug, 'title' => $product->currentDraftRevision->title,
            'short_description' => $product->currentDraftRevision->short_description, 'description_html' => $product->currentDraftRevision->description_html,
            'materials' => $product->currentDraftRevision->materials, 'fit' => $product->currentDraftRevision->fit, 'care' => $product->currentDraftRevision->care,
            'features' => $product->currentDraftRevision->features, 'images' => $images, 'colour_images' => $colourImages,
            'category' => $primaryCategory ? ['name' => $primaryCategory->name, 'slug' => $primaryCategory->slug] : null,
            'price' => $this->prices->format($product->base_price_minor, $product->currency),
            'compare_at_price' => $this->prices->format($product->compare_at_price_minor, $product->currency),
            'options' => $product->options->mapWithKeys(fn (ProductOption $option) => [$option->key => $option->values->map(fn (ProductOptionValue $value) => ['id' => $value->id, 'key' => $value->key, 'label' => $value->label, 'swatch_hex' => $value->swatch_hex])->values()->all()])->all(),
            'variants' => $product->variants->map(fn (ProductVariant $variant) => ['id' => $variant->id, 'sku' => $variant->sku, 'values' => $variant->values->pluck('id')->values()->all(), 'price' => $this->prices->format($this->prices->effectiveMinor($product, $variant), $product->currency)])->values()->all(),
            'default_variant_id' => $product->default_variant_id,
            'badges' => ProductBadge::query()->active()->where('product_id', $product->id)->orderBy('position')->pluck('badge_key')->all(),
            'related' => $related->map(fn ($relation) => $this->cards->present($relation->target))->filter()->values()->all(),
        ];
    }

    /**
     * @param  list<string>  $roles
     * @return list<array{url: string, alt: string}>
     */
    private function images(string $ownerType, string $ownerId, array $roles): array
    {
        return array_values(MediaUsage::query()->with('asset')->where('owner_type', $ownerType)->where('owner_identifier', $ownerId)->whereIn('field_role', $roles)->orderBy('sort_order')->get()->map(fn (MediaUsage $usage) => [
            'url' => $this->media->deliveryUrl($usage->asset->provider_public_id, $usage->asset->resource_type->value, 'product_gallery', $usage->asset->focal_x !== null ? (float) $usage->asset->focal_x : null, $usage->asset->focal_y !== null ? (float) $usage->asset->focal_y : null),
            'alt' => $usage->alt_text_override ?: $usage->asset->default_alt_text ?: $usage->asset->internal_title,
        ])->values()->all());
    }
}
