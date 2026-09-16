<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Catalogue\Support\ProductCardPresenter;
use App\Domain\Catalogue\Support\ProductPresenter;
use App\Domain\Homepage\Models\HomepageHero;

final class HomepageNewArrivalsPresenter
{
    public const CARD_LIMIT = 8;

    public function __construct(private ProductPresenter $products, private ProductCardPresenter $cards) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $fallback = [...HomepageHero::newArrivalsDefaults(), 'managed' => false, 'collection_id' => null, 'collection_name' => null, 'cta_url' => route('products.index', ['sort' => 'newest']), 'products' => [], 'total_count' => 0, 'ready_count' => 0, 'attention_count' => 0];
        if (! app(HomepageRenderSnapshot::class)->hasColumns(['new_arrivals_collection_id', 'new_arrivals_heading'])) {
            return $fallback;
        }

        $homepage = app(HomepageRenderSnapshot::class)->hero();
        if ($homepage === null || $homepage->new_arrivals_collection_id === null) {
            return $fallback;
        }
        $collection = Collection::query()->active()->whereKey($homepage->new_arrivals_collection_id)
            ->where('catalogue_status', 'ready')->with('currentDraftRevision')->first();
        if ($collection === null || $collection->currentDraftRevision === null) {
            return $fallback;
        }

        $memberships = CollectionProduct::query()->active()->where('collection_id', $collection->id)
            ->with('product.currentDraftRevision')->orderBy('position')->get();
        $this->cards->warmAvailability($memberships->pluck('product')->filter());
        $cards = $memberships->map(function (CollectionProduct $membership): ?array {
            $product = $membership->product;
            if ($product === null || $this->products->resolve($product->slug) === null) {
                return null;
            }

            return $this->cards->present($product);
        })->filter()->values();

        return [
            ...HomepageHero::newArrivalsDefaults(),
            ...$homepage->only(array_keys(HomepageHero::newArrivalsDefaults())),
            'managed' => true,
            'collection_id' => $collection->id,
            'collection_name' => $collection->currentDraftRevision->title,
            'cta_url' => route('collections.show', $collection),
            'products' => $cards->take(self::CARD_LIMIT)->all(),
            'total_count' => $memberships->count(),
            'ready_count' => $cards->count(),
            'attention_count' => $memberships->count() - $cards->count(),
        ];
    }
}
