<?php

namespace App\Http\Controllers;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\ProductPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema;

final class StorefrontProductController
{
    /** @var array<string, string> */
    private const TEMPLATES = [
        'the-taylor-oxford-shirt' => 'taylor-oxford-shirt',
        'mercerized-cotton-polo' => 'mercerized-cotton-polo',
        'the-dar-es-salaam-linen-suit' => 'dar-es-salaam-linen-suit',
        'slim-tapered-chinos' => 'slim-tapered-chinos',
        'the-executive-overcoat' => 'executive-overcoat',
    ];

    public function __invoke(string $product, ProductPresenter $presenter): View
    {
        return $this->render($product, $presenter);
    }

    public function show(Product $product, ProductPresenter $presenter): View
    {
        return $this->render($product->slug, $presenter, $product);
    }

    private function render(string $product, ProductPresenter $presenter, ?Product $boundProduct = null): View
    {
        $template = self::TEMPLATES[$product] ?? 'taylor-oxford-shirt';

        if (Schema::hasTable('products')) {
            $canonical = $boundProduct ?? Product::query()->where('slug', $product)->first();
            abort_if($canonical === null && ! array_key_exists($product, self::TEMPLATES), 404);
            abort_if($canonical !== null && ($canonical->archived_at !== null || $canonical->catalogue_status !== 'ready'), 404);
            $data = $canonical === null ? null : $presenter->resolve($product);
            abort_if($canonical !== null && $data === null && ! array_key_exists($product, self::TEMPLATES), 404);
        } else {
            $data = null;
        }

        return view('frontend.products.'.$template, [
            // The imported Product template retains this legacy variable name,
            // but the presenter payload is canonical for every Product slug.
            'oxfordProduct' => $data,
            'catalogueProduct' => $data,
            // The imported SPA only knows its original static route map. Dynamic
            // Laravel Products must retain the canonical server-rendered document.
            'loadImportedStorefrontRuntime' => array_key_exists($product, self::TEMPLATES),
        ]);
    }
}
