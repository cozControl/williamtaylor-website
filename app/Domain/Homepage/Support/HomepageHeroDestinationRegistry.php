<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Support\StorefrontShopNavigationPresenter;
use Illuminate\Support\Facades\Schema;

final class HomepageHeroDestinationRegistry
{
    /** @return array<string, string> */
    public function labels(): array
    {
        $labels = ['new_arrivals' => 'New Arrivals', 'collections' => 'Collections'];
        if (Schema::hasTable('collections')) {
            foreach (Collection::query()->active()->where('catalogue_status', 'ready')->whereHas('currentDraftRevision')->with('currentDraftRevision')->orderBy('navigation_order')->orderBy('slug')->get() as $collection) {
                $labels['collection:'.$collection->id] = 'Collection: '.$collection->currentDraftRevision->title;
            }
        }

        return $labels;
    }

    public function url(string $destination): string
    {
        if (str_starts_with($destination, 'collection:')) {
            $collection = Schema::hasTable('collections')
                ? Collection::query()->active()->where('catalogue_status', 'ready')->whereHas('currentDraftRevision')->find(substr($destination, strlen('collection:')))
                : null;

            return $collection === null ? route('collections.index') : route('collections.show', $collection->slug);
        }

        return match ($destination) {
            'new_arrivals' => app(StorefrontShopNavigationPresenter::class)->present()['new_arrivals']['url'] ?? route('products.index', ['sort' => 'newest']),
            'collections' => route('collections.index'),
            default => throw new \InvalidArgumentException("Unknown Homepage Hero destination [{$destination}]."),
        };
    }
}
