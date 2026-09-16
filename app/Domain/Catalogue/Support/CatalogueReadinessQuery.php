<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Query projection of CatalogueReadinessEvaluator for database counts/facets/pagination. */
final class CatalogueReadinessQuery
{
    /** @return Builder<Product> */
    public function query(): Builder
    {
        $query = Product::query()->active()->where('catalogue_status', 'ready')->where('product_type', 'apparel')
            ->whereNotNull('base_price_minor')
            ->whereHas('currentDraftRevision', fn ($q) => $q->whereColumn('product_revisions.product_id', 'products.id'))
            ->whereHas('categories', fn ($q) => $q->where('product_category_assignments.is_primary', true))
            ->whereHas('variants', fn ($q) => $q->whereNull('archived_at'))
            ->whereHas('defaultVariant', fn ($q) => $q->whereNull('archived_at')->whereColumn('product_variants.product_id', 'products.id')->whereNotNull('sku')->whereRaw("replace(replace(replace(replace(replace(replace(sku, char(32), ''), char(9), ''), char(10), ''), char(13), ''), char(0), ''), char(11), '') <> ''"))
            ->whereHas('options', fn ($q) => $q->whereNull('archived_at'), '<=', 2)
            ->whereDoesntHave('options', fn ($q) => $q->whereNull('archived_at')->whereNotIn('key', ['colour', 'size']))
            ->whereDoesntHave('variants', fn ($q) => $q->whereNull('archived_at')->whereRaw('(select count(*) from product_variant_values where variant_id = product_variants.id) <> (select count(*) from product_options where product_id = products.id and archived_at is null)'))
            ->whereHas('mediaUsages', fn ($q) => $q->where('field_role', 'primary'), '=', 1)
            ->whereHas('mediaUsages', fn ($q) => $q->where('field_role', 'gallery'), '<=', 20)
            ->whereDoesntHave('mediaUsages', fn ($q) => $q->whereNotIn('field_role', ['primary', 'gallery']))
            ->whereDoesntHave('mediaUsages', function ($q): void {
                $q->where(function ($bad): void {
                    $bad->where('decorative_override', true)->orWhereDoesntHave('asset', fn ($a) => $a->where('state', 'ready')->where('resource_type', 'image')->whereNotNull('confirmed_at'))
                        ->orWhereRaw("replace(replace(replace(replace(replace(replace(coalesce(alt_text_override, (select default_alt_text from media_assets where id = media_usages.media_asset_id), ''), char(32), ''), char(9), ''), char(10), ''), char(13), ''), char(0), ''), char(11), '') = ''");
                });
            });

        // Duplicate assets and non-contiguous gallery positions invalidate readiness.
        $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('media_usages')->where('owner_type', Product::class)
            ->whereColumn('owner_identifier', 'products.id')->groupBy('media_asset_id')->havingRaw('count(*) > 1'));
        $query->whereNotExists(fn ($q) => $q->selectRaw('1')->from('media_usages')->where('owner_type', Product::class)
            ->whereColumn('owner_identifier', 'products.id')->where('field_role', 'gallery')->groupBy('owner_identifier')
            ->havingRaw('min(sort_order) <> 0 or max(sort_order) <> count(*) - 1 or count(distinct sort_order) <> count(*)'));

        // Alt text is validated by ProductMediaAccessibility when written. Select only
        // the exceptional invalid usage owners, not the catalogue, for its PHP text rule.
        $invalidAltOwners = DB::table('media_usages as u')->join('media_assets as a', 'a.id', '=', 'u.media_asset_id')
            ->where('u.owner_type', Product::class)
            ->whereRaw("coalesce(u.alt_text_override, a.default_alt_text, '') like '%<%'")
            ->select(['u.owner_identifier', 'u.alt_text_override', 'a.default_alt_text'])->get()
            ->filter(fn ($u) => trim($u->alt_text_override ?? (string) $u->default_alt_text) !== strip_tags(trim($u->alt_text_override ?? (string) $u->default_alt_text)))
            ->pluck('owner_identifier')->unique()->all();

        return $query->whereNotIn('products.id', $invalidAltOwners);
    }
}
