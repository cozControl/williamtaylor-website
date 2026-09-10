<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Catalogue\Support\CollectionCardPresenter;
use App\Domain\Catalogue\Support\ProductCardPresenter;
use App\Domain\Catalogue\Support\ProductPresenter;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Media\Queries\ReadyImagePickerQuery;
use Illuminate\Support\Facades\Schema;

final class HomepageHandbagsPresenter
{
    public const CAPACITY = 6;

    public function __construct(private ProductPresenter $products, private ProductCardPresenter $cards, private CollectionCardPresenter $collections, private ReadyImagePickerQuery $images, private MediaProvider $media) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $result = [...HomepageHero::handbagsDefaults(), 'managed' => false, 'eligible' => false, 'collection' => null, 'products' => [], 'slides' => [], 'cta_url' => null, 'ready_count' => 0, 'attention_count' => 0];
        if (! Schema::hasColumns('homepage_heroes', ['handbags_managed', 'handbags_collection_id'])) {
            return $result;
        }
        $homepage = HomepageHero::find(HomepageHero::SINGLETON_ID);
        if ($homepage === null) {
            return $result;
        }
        $result = [...$result, ...$homepage->only(array_keys(HomepageHero::handbagsDefaults())), 'managed' => (bool) $homepage->handbags_managed];
        $collection = Collection::find($homepage->handbags_collection_id);
        if ($collection === null || ! ($identity = $this->collections->present($collection))['eligible']) {
            return $result;
        }
        $result['collection'] = $identity;
        $result['cta_url'] = route('collections.show', $collection);
        $memberships = CollectionProduct::query()->active()->where('collection_id', $collection->id)->with('product.currentDraftRevision')->orderBy('position')->get();
        $this->cards->warmAvailability($memberships->pluck('product')->filter());
        foreach ($memberships as $membership) {
            $product = $membership->product;
            $card = $product !== null && $this->products->resolve($product->slug) !== null ? $this->cards->present($product) : null;
            if ($card === null) {
                $result['attention_count']++;

                continue;
            }
            $result['ready_count']++;
            if (count($result['products']) < self::CAPACITY) {
                $result['products'][] = $card;
            }
        }
        $usages = MediaUsage::query()->where('owner_type', HomepageHero::class)->where('owner_identifier', $homepage->id)->whereIn('field_role', HomepageHero::HANDBAGS_MEDIA_ROLES)->get()->keyBy('field_role');
        foreach (HomepageHero::HANDBAGS_MEDIA_ROLES as $role) {
            $usage = $usages->get($role);
            $asset = $usage === null ? null : $this->images->findEligible($usage->media_asset_id);
            $alt = trim((string) ($usage?->alt_text_override ?: $asset?->default_alt_text));
            if ($asset !== null && $alt !== '') {
                $result['slides'][] = ['url' => $this->media->deliveryUrl($asset->provider_public_id, 'image', 'product_card', $asset->focal_x === null ? null : (float) $asset->focal_x, $asset->focal_y === null ? null : (float) $asset->focal_y), 'alt' => $alt];
            }
        }
        $result['eligible'] = count($result['slides']) > 0;

        return $result;
    }
}
