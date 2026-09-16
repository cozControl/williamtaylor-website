<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Support\CollectionCardPresenter;
use App\Domain\Homepage\Models\HomepageHero;

final class HomepageSummerEditPresenter
{
    public function __construct(private CollectionCardPresenter $cards) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $result = [...HomepageHero::summerEditDefaults(), 'managed' => false, 'eligible' => false, 'destination' => null];
        if (! app(HomepageRenderSnapshot::class)->hasColumns(['summer_edit_managed'])) {
            return $result;
        }
        $homepage = app(HomepageRenderSnapshot::class)->hero();
        if ($homepage === null) {
            return $result;
        }
        $id = $homepage->getAttribute('summer_edit_collection_id');
        $collection = is_string($id) ? Collection::find($id) : null;
        $card = $collection === null ? null : $this->cards->present($collection);

        return [...$result, ...$homepage->only(array_keys(HomepageHero::summerEditDefaults())), 'managed' => (bool) $homepage->getAttribute('summer_edit_managed'), 'eligible' => (bool) ($card['eligible'] ?? false), 'destination' => $card];
    }
}
