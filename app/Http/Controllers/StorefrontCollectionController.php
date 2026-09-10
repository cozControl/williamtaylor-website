<?php

namespace App\Http\Controllers;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Support\CollectionMediaRoleRegistry;
use App\Domain\Catalogue\Support\ProductCardPresenter;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;
use Illuminate\Contracts\View\View;

final class StorefrontCollectionController
{
    public function __invoke(Collection $collection, ProductCardPresenter $cards, MediaProvider $media): View
    {
        abort_if($collection->archived_at !== null || $collection->catalogue_status !== 'ready', 404);
        $collection->load(['currentDraftRevision', 'products' => fn ($query) => $query->active()->with('product.currentDraftRevision')]);
        abort_if($collection->currentDraftRevision === null, 404);
        $usage = MediaUsage::query()->with('asset')->where('owner_type', Collection::class)->where('owner_identifier', $collection->id)->where('field_role', CollectionMediaRoleRegistry::CARD)->first();
        $image = $usage === null ? null : $media->deliveryUrl($usage->asset->provider_public_id, $usage->asset->resource_type->value, 'hero_desktop', null, null);
        $cards->warmAvailability($collection->products->pluck('product')->filter());
        $products = $collection->products->map(fn ($membership) => $cards->present($membership->product))->filter()->values();

        return view('frontend.collection-show', compact('collection', 'image', 'products'))->with('loadImportedStorefrontRuntime', false);
    }
}
