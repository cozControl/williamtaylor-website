<?php

namespace App\Http\Controllers;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\ProductCardPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class StorefrontWishlistController
{
    public function __invoke(Request $request, CatalogueReadinessEvaluator $readiness, ProductCardPresenter $cards): View
    {
        $input = $request->validate(['items' => ['array', 'max:100'], 'items.*' => ['string', 'max:160', 'distinct'], 'fragment' => ['nullable', 'boolean']]);
        $products = empty($input['items']) ? collect() : $readiness->publicQuery()->whereIn('slug', $input['items'])
            ->with(['currentDraftRevision', 'options.values', 'mediaUsages.asset', 'badges'])->limit(100)->get();
        $cards->warmAvailability($products);
        $wishlistCards = $products->map(fn (Product $product) => $cards->present($product));

        return view($request->boolean('fragment') ? 'frontend.partials.wishlist-items' : 'frontend.wishlist', compact('wishlistCards'))->with('loadImportedStorefrontRuntime', false);
    }
}
