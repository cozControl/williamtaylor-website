<?php

namespace App\Http\Controllers;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Queries\StorefrontCatalogueQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class StorefrontCollectionController
{
    public function __invoke(Request $request, Collection $collection, StorefrontCatalogueQuery $catalogue): View
    {
        abort_if($collection->archived_at !== null || $collection->catalogue_status !== 'ready', 404);
        $collection->load('currentDraftRevision');
        abort_if($collection->currentDraftRevision === null, 404);

        return view('frontend.collection-show', $catalogue->resolve($request, $collection))->with('loadImportedStorefrontRuntime', false);
    }
}
