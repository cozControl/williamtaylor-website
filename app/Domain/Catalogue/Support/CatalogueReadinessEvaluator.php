<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Data\CatalogueReadinessResult;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Media\Models\MediaUsage;

final class CatalogueReadinessEvaluator
{
    public function __construct(
        private ProductTypeRegistry $types,
        private ProductMediaAccessibility $accessibility,
    ) {}

    public function evaluate(Product $p): CatalogueReadinessResult
    {
        $f = [];
        if ($p->archived_at) {
            $f[] = 'product_archived';
        }try {
            $this->types->get($p->product_type);
        } catch (\Throwable) {
            $f[] = 'unsupported_product_type';
        }$revision = $p->currentDraftRevision;
        if (! $revision) {
            $f[] = 'missing_current_revision';
        } elseif ($revision->product_id !== $p->id) {
            $f[] = 'invalid_revision_ownership';
        }$options = $p->options()->active()->with('values')->get();
        if ($options->count() > 2 || $options->contains(fn ($o) => ! in_array($o->key, ['colour', 'size'], true))) {
            $f[] = 'invalid_option_configuration';
        }$variants = $p->variants()->active()->with('values')->get();
        if ($variants->isEmpty()) {
            $f[] = 'missing_variants';
        }foreach ($variants as $v) {
            if ($v->values->count() !== $options->count()) {
                $f[] = 'incomplete_variant_combination';
                break;
            }
        }if ($variants->isNotEmpty() && ! $p->defaultVariant) {
            $f[] = 'missing_default_variant';
        } elseif ($p->defaultVariant && ($p->defaultVariant->product_id !== $p->id || $p->defaultVariant->archived_at)) {
            $f[] = 'invalid_default_variant';
        }

        $media = MediaUsage::query()
            ->with('asset')
            ->where('owner_type', Product::class)
            ->where('owner_identifier', $p->id)
            ->get();
        $primary = $media->where('field_role', ProductMediaRoleRegistry::PRIMARY);
        if ($primary->count() !== 1) {
            $f[] = 'missing_primary_media';
        } elseif (! $this->accessibility->isUsable($primary->first())) {
            $f[] = 'unusable_primary_media';
        }

        $gallery = $media->where('field_role', ProductMediaRoleRegistry::GALLERY)->sortBy('sort_order')->values();
        if ($gallery->count() > 20) {
            $f[] = 'gallery_media_limit_exceeded';
        }
        if ($gallery->isNotEmpty() && $gallery->pluck('sort_order')->all() !== range(0, $gallery->count() - 1)) {
            $f[] = 'invalid_gallery_order';
        }
        if ($gallery->contains(fn (MediaUsage $usage): bool => ! $this->accessibility->isUsable($usage))) {
            $f[] = 'unusable_gallery_media';
        }
        if ($media->pluck('media_asset_id')->duplicates()->isNotEmpty()) {
            $f[] = 'duplicate_product_media';
        }
        if ($media->contains(fn (MediaUsage $usage): bool => ! in_array($usage->field_role, [ProductMediaRoleRegistry::PRIMARY, ProductMediaRoleRegistry::GALLERY], true))) {
            $f[] = 'unsupported_product_media_role';
        }

        $f = array_values(array_unique($f));
        sort($f);

        return new CatalogueReadinessResult($f === [], $f, array_map(fn ($x) => str_replace('_', ' ', $x), $f));
    }
}
