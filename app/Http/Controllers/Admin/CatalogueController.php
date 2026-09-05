<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\ProductMediaRoleRegistry;
use App\Domain\Identity\Support\PermissionRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

final class CatalogueController
{
    public function __invoke(): View
    {
        Gate::authorize(PermissionRegistry::PRODUCTS_VIEW);

        $products = Product::query()->whereNull('archived_at');
        $needsAttention = Product::query()->whereNull('archived_at')
            ->where(function ($query): void {
                $query->whereNull('base_price_minor')
                    ->orWhereNull('current_draft_revision_id')
                    ->orWhereNull('default_variant_id')
                    ->orWhereDoesntHave('categories')
                    ->orWhereDoesntHave('variants', fn ($variants) => $variants->whereNull('archived_at')->whereNotNull('sku')->where('sku', '<>', ''))
                    ->orWhereNotExists(function ($media): void {
                        $media->selectRaw('1')->from('media_usages')
                            ->whereColumn('media_usages.owner_identifier', 'products.id')
                            ->where('media_usages.owner_type', Product::class)
                            ->where('media_usages.field_role', ProductMediaRoleRegistry::PRIMARY);
                    });
            })->count();

        return view('admin.catalogue.index', [
            'metrics' => [
                'products' => (clone $products)->count(),
                'active_products' => (clone $products)->where('catalogue_status', 'ready')->count(),
                'categories' => ProductCategory::query()->whereNull('archived_at')->count(),
                'collections' => Collection::query()->whereNull('archived_at')->count(),
                'variants' => ProductVariant::query()->whereNull('archived_at')->whereHas('product', fn ($product) => $product->whereNull('archived_at'))->count(),
                'needs_attention' => $needsAttention,
            ],
        ]);
    }
}
