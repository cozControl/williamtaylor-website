<?php

namespace App\Domain\Catalogue\Queries;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\ProductCardPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class StorefrontCatalogueQuery
{
    public function __construct(private CatalogueReadinessEvaluator $readiness, private ProductCardPresenter $cards) {}

    /** @return array<string, mixed> */
    public function resolve(Request $request, ?Collection $collection = null): array
    {
        $input = $request->query();
        if (isset($input['size']) && is_string($input['size'])) {
            $input['size'] = [$input['size']];
        }
        $state = validator($input, [
            'category' => ['nullable', 'string', 'max:321'],
            'size' => ['nullable', 'array', 'max:20'], 'size.*' => ['string', 'max:80', 'distinct'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['featured', 'newest', 'price-asc', 'price-desc', 'bestselling'])],
            'view' => ['nullable', Rule::in(['grid', 'list'])],
            'filters' => ['nullable', Rule::in(['1'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ])->validate();
        $state = array_filter($state, fn ($value) => $value !== null && $value !== '' && $value !== []);
        $base = $this->readiness->publicQuery()
            ->when($collection, fn ($q) => $q->whereHas('collectionMemberships', fn ($m) => $m->whereNull('archived_at')->where('collection_id', $collection->id)));
        $categories = ProductCategory::query()->whereNull('archived_at')->where('is_visible', true)
            ->when($collection, fn ($q) => $q->where('collection_id', $collection->id))
            ->whereHas('collection', fn ($q) => $q->whereNull('archived_at')->where('catalogue_status', 'ready')->whereHas('currentDraftRevision'))
            ->whereHas('products', fn ($q) => $q->whereIn('products.id', (clone $base)->select('products.id')))
            ->with('collection.currentDraftRevision')->orderBy('position')->orderBy('name')->get();
        $categoryKey = fn (ProductCategory $category): string => $collection ? $category->slug : $category->collection->slug.'/'.$category->slug;
        $selectedCategory = $categories->first(fn (ProductCategory $c) => $categoryKey($c) === ($state['category'] ?? null));
        if ($selectedCategory === null) {
            unset($state['category']);
        }
        $sizes = ProductOptionValue::query()->active()->where('is_active', true)
            ->whereHas('option', fn ($q) => $q->whereNull('archived_at')->where('key', 'size')->whereIn('product_id', (clone $base)->select('products.id')))
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('product_variant_values as vv')->join('product_variants as v', 'v.id', '=', 'vv.variant_id')->whereColumn('vv.product_option_value_id', 'product_option_values.id')->whereNull('v.archived_at'))
            ->select(['key', 'label'])->distinct()->orderBy('key')->get();
        $selectedSizes = array_values(array_intersect($state['size'] ?? [], $sizes->pluck('key')->all()));
        if ($selectedSizes === []) {
            unset($state['size']);
        } else {
            $state['size'] = $selectedSizes;
        }
        $total = (clone $base)->count();
        $query = (clone $base)
            ->when($selectedCategory, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($selectedCategory->id)))
            ->when($selectedSizes !== [], fn ($q) => $q->whereHas('variants', fn ($v) => $v->whereNull('archived_at')->whereHas('values', fn ($values) => $values->whereNull('archived_at')->where('is_active', true)->whereIn('key', $selectedSizes)->whereHas('option', fn ($o) => $o->whereNull('archived_at')->where('key', 'size')))))
            ->when(filled($state['q'] ?? null), function ($q) use ($state): void {
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($state['q'])).'%';
                $q->whereHas('currentDraftRevision', fn ($r) => $r->whereRaw("title like ? escape '!'", [$pattern])->orWhereRaw("short_description like ? escape '!'", [$pattern]));
            });
        $sort = $state['sort'] ?? 'featured';
        if (in_array($sort, ['price-asc', 'price-desc'], true)) {
            $query->orderBy('base_price_minor', $sort === 'price-asc' ? 'asc' : 'desc');
        } elseif ($sort === 'newest') {
            $query->withExists(['badges as preferred_badge' => fn ($b) => $b->active()->where('badge_key', 'new')])->orderByDesc('preferred_badge')->orderByDesc('products.created_at');
        } elseif ($sort === 'bestselling') {
            // Rank by canonical paid Order quantities; do not fabricate Bestseller badges.
            $query->orderByDesc(DB::table('commerce_order_lines as sale')->join('commerce_orders as orders', 'orders.id', '=', 'sale.order_id')
                ->selectRaw('coalesce(sum(sale.quantity), 0)')->whereColumn('sale.product_id', 'products.id')->where('orders.payment_status', 'paid'));
        } elseif ($collection) {
            $query->orderByRaw('(select min(position) from collection_products where collection_id = ? and product_id = products.id and archived_at is null)', [$collection->id]);
        }
        $baseUrl = $collection ? route('collections.show', $collection->slug) : route('products.index');
        $products = $query->orderBy('products.id')->with(['currentDraftRevision', 'options.values', 'mediaUsages.asset', 'badges'])->paginate(12)->withPath($baseUrl)->appends($state);
        $this->cards->warmAvailability($products->items());
        $productCards = $products->getCollection()->map(fn (Product $p) => $this->cards->present($p));
        $listingUrl = function (array $changes = []) use ($state, $baseUrl): string {
            $query = array_filter(array_replace($state, ['page' => null], $changes), fn ($v) => $v !== null && $v !== '' && $v !== []);

            return $baseUrl.($query ? '?'.http_build_query($query) : '');
        };

        $catalogueSeo = ['title' => ($collection?->currentDraftRevision->title ?? 'Shop All').' | William Taylor', 'description' => $collection?->currentDraftRevision?->short_description ?: 'Explore the William Taylor catalogue.', 'url' => $baseUrl, 'robots' => isset($state['category']) || isset($state['size']) || isset($state['q']) ? 'noindex,follow' : 'index,follow'];

        return compact('catalogueSeo', 'products', 'productCards', 'categories', 'selectedCategory', 'selectedSizes', 'sizes', 'categoryKey', 'state', 'baseUrl', 'listingUrl', 'total', 'collection');
    }
}
