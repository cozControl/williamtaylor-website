<?php

namespace App\Http\Controllers;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Support\CollectionCardPresenter;
use Illuminate\Contracts\View\View;

final class StorefrontCollectionsController
{
    public function __invoke(CollectionCardPresenter $presenter): View
    {
        $collections = Collection::query()->active()->where('catalogue_status', 'ready')->whereHas('currentDraftRevision')->with(['currentDraftRevision', 'products.product.currentDraftRevision'])->orderBy('navigation_order')->orderBy('slug')->get()->map(fn (Collection $collection): array => $presenter->present($collection))->filter(fn (array $card): bool => $card['eligible'])->values();

        return view('frontend.collections', compact('collections'))->with('loadImportedStorefrontRuntime', false);
    }
}
