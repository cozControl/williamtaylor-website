<?php

namespace App\Http\Controllers;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Queries\StorefrontCatalogueQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class StorefrontShopController
{
    public function __invoke(Request $request, StorefrontCatalogueQuery $catalogue): View|RedirectResponse
    {
        if ($request->has('collection')) {
            $slug = $request->validate(['collection' => ['required', 'string', 'max:160']])['collection'];
            $collection = Collection::query()->active()->where('catalogue_status', 'ready')->where('slug', $slug)->firstOrFail();

            return redirect()->route('collections.show', ['collection' => $collection->slug, ...$request->except('collection')], 301);
        }

        return view('frontend.shop', $catalogue->resolve($request))->with('loadImportedStorefrontRuntime', false);
    }
}
