<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Data\CollectionReadinessResult;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Media\Models\MediaUsage;

final class CollectionReadinessEvaluator
{
    public function __construct(private CollectionTypeRegistry $types, private CollectionContentSchema $content, private CollectionMediaAccessibility $accessibility, private CatalogueReadinessEvaluator $products) {}

    public function evaluate(Collection $collection): CollectionReadinessResult
    {
        $failures = [];
        if ($collection->archived_at) {
            $failures[] = 'collection_archived';
        }
        try {
            $this->types->get($collection->collection_type);
            CollectionSlug::normalize($collection->slug);
        } catch (\Throwable) {
            $failures[] = 'invalid_collection_identity';
        }
        $revision = $collection->currentDraftRevision;
        try {
            if (! $revision || $revision->collection_id !== $collection->id) {
                throw new \RuntimeException;
            }
            $this->content->normalize($revision->only(['title', 'short_description']));
        } catch (\Throwable) {
            $failures[] = 'missing_or_invalid_current_revision';
        }
        $media = MediaUsage::query()->with('asset')->where('owner_type', Collection::class)->where('owner_identifier', $collection->id)->get();
        foreach ([CollectionMediaRoleRegistry::CARD, CollectionMediaRoleRegistry::HERO] as $role) {
            $roleMedia = $media->where('field_role', $role);
            if ($roleMedia->count() !== 1) {
                $failures[] = 'missing_'.$role.'_media';
            } elseif (! $this->accessibility->isUsable($roleMedia->first())) {
                $failures[] = 'unusable_'.$role.'_media';
            }
        }
        if ($media->contains(fn (MediaUsage $usage): bool => ! in_array($usage->field_role, [CollectionMediaRoleRegistry::CARD, CollectionMediaRoleRegistry::HERO], true))) {
            $failures[] = 'unsupported_collection_media_role';
        }
        $memberships = CollectionProduct::query()->active()->with('product')->where('collection_id', $collection->id)->orderBy('position')->get();
        if ($memberships->isEmpty()) {
            $failures[] = 'empty_collection';
        } elseif ($memberships->pluck('position')->all() !== range(0, $memberships->count() - 1)) {
            $failures[] = 'invalid_membership_order';
        }
        if ($memberships->pluck('product_id')->duplicates()->isNotEmpty()) {
            $failures[] = 'duplicate_membership';
        }
        foreach ($memberships as $membership) {
            if (! $membership->product || $membership->product->archived_at) {
                $failures[] = 'archived_product_target';
            } elseif (! $this->products->evaluate($membership->product)->ready) {
                $failures[] = 'product_target_not_catalogue_ready';
            }
        }
        $failures = array_values(array_unique($failures));
        sort($failures);

        return new CollectionReadinessResult($failures === [], $failures, array_map(fn (string $code): string => str_replace('_', ' ', $code), $failures));
    }
}
