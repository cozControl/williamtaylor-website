<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Support\CollectionCardPresenter;
use App\Domain\Homepage\Models\HomepageHero;
use Illuminate\Support\Facades\Schema;

final class HomepageExploreCollectionsPresenter
{
    public const CAPACITY = 3;

    public function __construct(private CollectionCardPresenter $cards) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $fallback = [
            ...HomepageHero::exploreCollectionsDefaults(),
            'managed' => false,
            'collections' => [],
            'configured_count' => 0,
            'eligible_count' => 0,
            'attention_count' => 0,
        ];
        if (! Schema::hasTable('collections') || ! Schema::hasColumns('homepage_heroes', ['explore_collections_managed', 'explore_collection_3_id'])) {
            return $fallback;
        }

        $homepage = HomepageHero::query()->whereKey(HomepageHero::SINGLETON_ID)->first();
        if ($homepage === null || ! $homepage->explore_collections_managed) {
            return $fallback;
        }

        $configured = 0;
        $items = collect(range(1, self::CAPACITY))->map(function (int $position) use ($homepage, &$configured): ?array {
            $id = $homepage->{"explore_collection_{$position}_id"};
            if (! is_string($id)) {
                return null;
            }

            $configured++;
            $collection = Collection::query()
                ->with(['currentDraftRevision', 'products.product.currentDraftRevision'])
                ->find($id);
            if ($collection === null) {
                return null;
            }

            $card = $this->cards->present($collection);

            return $card['eligible'] ? [...$card, 'position' => $position] : null;
        })->filter()->values()->all();

        return [
            ...$fallback,
            ...$homepage->only(array_keys(HomepageHero::exploreCollectionsDefaults())),
            'managed' => true,
            'collections' => $items,
            'configured_count' => $configured,
            'eligible_count' => count($items),
            'attention_count' => $configured - count($items),
        ];
    }
}
