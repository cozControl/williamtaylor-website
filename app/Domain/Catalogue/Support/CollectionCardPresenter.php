<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionRevision;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;

final class CollectionCardPresenter
{
    public function __construct(
        private MediaProvider $media,
        private ProductCardPresenter $products,
        private CollectionMediaAccessibility $accessibility,
    ) {}

    /** @return array<string, mixed> */
    public function present(Collection $collection): array
    {
        $collection->loadMissing(['currentDraftRevision', 'products.product.currentDraftRevision']);
        $usage = MediaUsage::query()->with('asset')
            ->where('owner_type', Collection::class)
            ->where('owner_identifier', $collection->id)
            ->where('field_role', CollectionMediaRoleRegistry::CARD)
            ->first();
        $revision = $collection->getRelationValue('currentDraftRevision');
        $mediaIsUsable = $usage !== null && $this->accessibility->isUsable($usage);
        $eligibleProducts = $collection->products
            ->filter(fn ($membership): bool => $membership->product !== null && $this->products->present($membership->product) !== null)
            ->count();
        $productCount = $collection->products->count();
        $isPublic = $collection->archived_at === null
            && $collection->catalogue_status === 'ready'
            && $revision instanceof CollectionRevision
            && $mediaIsUsable;
        $image = null;
        if ($usage !== null && $mediaIsUsable) {
            $asset = $usage->asset;
            $image = [
                'url' => $this->media->deliveryUrl(
                    $asset->provider_public_id,
                    $asset->resource_type->value,
                    'collection_card',
                    $asset->focal_x !== null ? (float) $asset->focal_x : null,
                    $asset->focal_y !== null ? (float) $asset->focal_y : null,
                ),
                'alt' => trim($usage->alt_text_override ?: (string) $asset->default_alt_text),
            ];
        }

        return [
            'id' => $collection->id,
            'slug' => $collection->slug,
            'title' => $revision instanceof CollectionRevision ? $revision->title : $collection->slug,
            'description' => $revision instanceof CollectionRevision ? $revision->short_description : null,
            'url' => route('collections.show', $collection->slug),
            'image' => $image,
            'visibility' => $collection->archived_at !== null ? 'Archived' : ($collection->catalogue_status === 'ready' ? 'Visible' : 'Hidden'),
            'status' => $isPublic && $eligibleProducts > 0 ? 'Ready for storefront' : 'Needs attention',
            'eligible' => $isPublic,
            'product_count' => $productCount,
            'ready_count' => $eligibleProducts,
            'attention_count' => $productCount - $eligibleProducts,
        ];
    }
}
