<?php

namespace App\Domain\Cart;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\ProductMediaAccessibility;
use App\Domain\Catalogue\Support\ProductPrice;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;

final class CartPresenter
{
    public function __construct(private InventoryAvailabilityService $inventory, private CatalogueReadinessEvaluator $readiness, private ProductPrice $prices, private MediaProvider $media) {}

    /** @param array<string, int> $requested
     * @return array{lines: list<array<string, mixed>>, item_count: int, subtotal_minor: int|null, subtotal: string, money_issue: bool, is_empty: bool, is_checkout_ready: bool, issues: list<string>}
     */
    public function present(array $requested): array
    {
        $lines = [];
        $subtotal = 0;
        $moneyIssue = false;
        if ($requested !== []) {
            $variants = ProductVariant::query()->whereIn('id', array_keys($requested))->with(['values.option', 'product.currentDraftRevision', 'product.categories', 'product.options.values', 'product.variants.values', 'product.defaultVariant'])->get()->keyBy('id');
            $productIds = $variants->pluck('product_id')->unique()->all();
            $colourIds = $variants->flatMap(fn ($variant) => $variant->values->where('option.key', 'colour')->pluck('id'))->all();
            $media = MediaUsage::query()->with('asset')->where(function ($query) use ($productIds, $colourIds) {
                $query->where(fn ($q) => $q->where('owner_type', Product::class)->whereIn('owner_identifier', $productIds))
                    ->orWhere(fn ($q) => $q->where('owner_type', ProductOptionValue::class)->whereIn('owner_identifier', $colourIds));
            })->get()->groupBy('owner_identifier');
            $location = StockLocation::query()->where('code', 'MAIN')->first();
            $stock = $location ? $this->inventory->summaries($variants, $location) : [];
            $ready = [];
            foreach ($variants->pluck('product')->filter()->unique('id') as $product) {
                $ready[$product->id] = $product->catalogue_status === 'ready' && $this->readiness->evaluate($product, $media->get($product->id, collect()))->ready;
            }
            foreach ($requested as $id => $quantity) {
                $variant = $variants->get($id);
                $product = $variant?->product;
                $eligible = $product && ($ready[$product->id] ?? false) && ! $variant->archived_at && filled($variant->sku);
                if ($eligible) {
                    $optionIds = $product->options->whereNull('archived_at')->pluck('id')->sort()->values()->all();
                    $assigned = $variant->values->pluck('product_option_id')->sort()->values()->all();
                    $eligible = $assigned === $optionIds && ! $variant->values->contains(fn ($value) => $value->archived_at || ! $value->is_active || $value->option->product_id !== $product->id);
                }
                $minor = $product ? $this->prices->effectiveMinor($product, $variant) : null;
                $validPrice = is_int($minor) && $minor >= 0 && $product->currency === 'TZS';
                $available = $eligible ? ($stock[$id]['available'] ?? 0) : 0;
                $issue = ! $eligible ? 'This option is no longer available.' : (! $validPrice ? 'This item cannot currently be priced.' : ($available === 0 ? 'This item is currently out of stock.' : ($quantity > $available ? "Only {$available} are currently available. Reduce the quantity or remove this item." : null)));
                $total = $validPrice && ($minor === 0 || $quantity <= intdiv(PHP_INT_MAX, $minor)) ? $minor * $quantity : null;
                if ($total === null || $total > PHP_INT_MAX - $subtotal) {
                    $moneyIssue = true;
                    $issue ??= 'This quantity cannot currently be priced.';
                } else {
                    $subtotal += $total;
                }
                $image = null;
                if ($product) {
                    $colour = $variant->values->first(fn ($value) => $value->option->key === 'colour');
                    $accessibility = app(ProductMediaAccessibility::class);
                    $usage = ($colour ? $media->get($colour->id, collect())->first(fn (MediaUsage $item) => $item->field_role === 'colour_primary' && $accessibility->isUsable($item)) : null)
                        ?? $media->get($product->id, collect())->first(fn (MediaUsage $item) => $item->field_role === 'primary' && $accessibility->isUsable($item));
                    if ($usage?->asset) {
                        $image = ['url' => $this->media->deliveryUrl($usage->asset->provider_public_id, $usage->asset->resource_type->value, 'product_gallery'), 'alt' => $usage->alt_text_override ?: $usage->asset->default_alt_text ?: $usage->asset->internal_title];
                    }
                }
                $lines[] = ['variant_id' => $id, 'title' => $product?->currentDraftRevision->title ?? 'Unavailable item', 'url' => $eligible ? route('products.show', $product->slug) : null, 'image' => $image,
                    'options' => $variant?->values->map(fn ($value) => $value->option->label.': '.$value->label)->all() ?? [], 'sku' => $variant?->sku,
                    'quantity' => $quantity, 'unit_price_minor' => $validPrice ? $minor : null, 'unit_price' => $validPrice ? $this->format($minor) : null,
                    'line_total_minor' => $total, 'line_total' => $total !== null ? $this->format($total) : null, 'available_to_sell' => $available, 'is_available' => $eligible && $available > 0, 'issue' => $issue];
            }
        }
        $issues = array_values(array_filter(array_column($lines, 'issue')));

        return ['lines' => $lines, 'item_count' => array_sum($requested), 'subtotal_minor' => $moneyIssue ? null : $subtotal, 'subtotal' => $moneyIssue ? 'Unavailable' : $this->format($subtotal), 'money_issue' => $moneyIssue, 'is_empty' => $lines === [], 'is_checkout_ready' => $lines !== [] && $issues === [] && ! $moneyIssue, 'issues' => $issues];
    }

    public function format(int $minor): string
    {
        // Match the whole-shilling storefront format without floating-point arithmetic.
        $whole = intdiv($minor, 100) + ($minor % 100 >= 50 ? 1 : 0);

        return 'TZS '.preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', (string) $whole);
    }
}
