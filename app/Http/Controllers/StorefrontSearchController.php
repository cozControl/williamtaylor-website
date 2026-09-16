<?php

namespace App\Http\Controllers;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductCardPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class StorefrontSearchController
{
    public function __invoke(Request $request, ProductCardPresenter $cards): View
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $query = trim($validated['q'] ?? '');
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%';
        $products = Product::query()->active()->where('catalogue_status', 'ready')
            ->whereNotNull('base_price_minor')
            ->whereHas('currentDraftRevision', fn ($builder) => $builder->whereRaw("title like ? escape '!'", [$pattern]))
            ->when($query === '', fn ($builder) => $builder->whereRaw('1 = 0'))
            ->with('currentDraftRevision')->orderBy('slug')->paginate(12)->withQueryString();
        $cards->warmAvailability($products->items());
        $productCards = $products->getCollection()->map(fn (Product $product) => $cards->present($product))->filter();

        return view('frontend.search', compact('query', 'products', 'productCards'))->with('loadImportedStorefrontRuntime', false);
    }
}
