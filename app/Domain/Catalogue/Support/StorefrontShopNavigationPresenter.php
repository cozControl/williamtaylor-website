<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageSectionVisibility;
use App\Domain\PublicProjection\Data\PublicNavigationItemView;
use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;
use Illuminate\Support\Facades\Schema;

final class StorefrontShopNavigationPresenter
{
    /** @return array<string, mixed> */
    public function present(): array
    {
        if (request()->attributes->has('storefront.shop_navigation')) {
            return request()->attributes->get('storefront.shop_navigation');
        }
        $items = [];
        $footerCollections = [];
        $collectionUrls = [];
        $visibility = app(HomepageSectionVisibility::class)->resolve();
        $newArrivals = null;
        if (Schema::hasColumns('collections', ['navigation_order'])) {
            $selectedId = Schema::hasColumns('homepage_heroes', ['new_arrivals_collection_id']) ? HomepageHero::whereKey(HomepageHero::SINGLETON_ID)->value('new_arrivals_collection_id') : null;
            $collections = Collection::query()->active()->where('catalogue_status', 'ready')->whereHas('currentDraftRevision')->with('currentDraftRevision')->orderBy('navigation_order')->orderBy('slug')->get();
            foreach ($collections as $collection) {
                $current = request()->route('collection');
                $entry = $this->entry($collection->currentDraftRevision->title, route('collections.show', $collection->slug), request()->routeIs('collections.show') && $current instanceof Collection && $current->id === $collection->id);
                $collectionUrls[$collection->slug] = $entry['url'];
                $footerCollections[] = $entry;
                if ($collection->id === $selectedId || ($selectedId === null && $collection->slug === 'new-arrivals')) {
                    $newArrivals = [...$entry, 'label' => 'New Arrivals'];
                } else {
                    $items[] = $entry;
                }
            }
        }
        $all = $this->entry('All Collections', route('collections.index'), request()->routeIs('collections.index'));
        $preorder = $this->entry('Pre-Order', route('preorders.index'), request()->routeIs('preorders.*'));
        $limited = $this->entry('Limited Edition', route('limited-edition.index'), request()->routeIs('limited-edition.*'));
        $special = array_values(array_filter([
            $visibility['new-arrivals'] ? $newArrivals : null,
            $visibility['future-style'] ? $preorder : null,
            $visibility['limited-edition'] ? $limited : null,
        ]));
        $topLinks = $special;
        if ($visibility['explore-collections']) {
            array_unshift($topLinks, [...$all, 'label' => 'Collections']);
        }
        $editorial = [];
        $shopLabel = 'Shop';
        foreach (app(ResolvePublicSiteChrome::class)->resolve()->navigation->items ?? [] as $item) {
            $path = rtrim(parse_url($item->link->url, PHP_URL_PATH) ?: '/', '/');
            $local = parse_url($item->link->url, PHP_URL_HOST) === null || parse_url($item->link->url, PHP_URL_HOST) === parse_url(url('/'), PHP_URL_HOST);
            if ($local && ($path === '/shop' || ($item->key === 'shop' && $path === '/collections'))) {
                $shopLabel = $item->link->label;
                array_push($editorial, ...$this->editorialItems($item->children));

                continue;
            }
            if ($local && ($path === '/collections' || str_starts_with($path, '/collections/') || in_array($path, ['/pre-order', '/limited-edition'], true))) {
                array_push($editorial, ...$this->editorialItems($item->children));

                continue;
            }
            $editorial[] = new PublicNavigationItemView($item->key, $item->link, $item->visibility, $this->editorialItems($item->children));
        }
        $result = ['label' => $shopLabel, 'active' => request()->routeIs('products.index', 'collections.*', 'preorders.*', 'limited-edition.*'), 'all_collections' => $all, 'collections' => $items, 'footer_collections' => $footerCollections, 'new_arrivals' => $newArrivals, 'pre_order' => $preorder, 'limited_edition' => $limited, 'special' => $special, 'top_links' => $topLinks, 'editorial' => $editorial, 'collection_urls' => $collectionUrls];
        request()->attributes->set('storefront.shop_navigation', $result);

        return $result;
    }

    public function canonicalCollectionLink(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null && $host !== false && $host !== parse_url(url('/'), PHP_URL_HOST)) {
            return $url;
        }
        $path = '/'.ltrim((string) parse_url($url, PHP_URL_PATH), '/');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $slug = null;
        if (preg_match('~^/collections/([^/]+)$~', $path, $matches)) {
            $slug = $matches[1];
        }
        if ($path === '/shop' && is_string($query['collection'] ?? null)) {
            $slug = $query['collection'];
        }
        if (preg_match('~^/html/(mens-wear|womens-wear|unisex|accessories|shoes|handbags|new-arrivals)\.html$~', $path, $matches)) {
            $slug = $matches[1];
        }

        return $slug === null ? $url : ($this->present()['collection_urls'][$slug] ?? route('collections.index'));
    }

    /** @return array{label: string, url: string, active: bool} */
    private function entry(string $label, string $url, bool $active): array
    {
        return compact('label', 'url', 'active');
    }

    /** @param list<PublicNavigationItemView> $items
     * @return list<PublicNavigationItemView>
     */
    private function editorialItems(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $path = rtrim(parse_url($item->link->url, PHP_URL_PATH) ?: '/', '/');
            $host = parse_url($item->link->url, PHP_URL_HOST);
            $local = $host === null || $host === parse_url(url('/'), PHP_URL_HOST);
            $children = $this->editorialItems($item->children);
            if ($local && (in_array($path, ['/shop', '/collections', '/pre-order', '/limited-edition'], true) || str_starts_with($path, '/collections/'))) {
                array_push($result, ...$children);
            } else {
                $result[] = new PublicNavigationItemView($item->key, $item->link, $item->visibility, $children);
            }
        }

        return $result;
    }
}
