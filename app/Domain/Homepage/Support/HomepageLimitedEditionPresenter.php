<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Support\LimitedEditionCampaignPresenter;
use App\Domain\Homepage\Models\HomepageHero;

final class HomepageLimitedEditionPresenter
{
    public const CAPACITY = 3;

    public function __construct(private LimitedEditionCampaignPresenter $campaigns) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $fallback = [...HomepageHero::limitedEditionDefaults(), 'managed' => false, 'campaigns' => []];
        if (! app(HomepageRenderSnapshot::class)->hasColumns(['limited_edition_managed', 'limited_edition_campaign_3_id'])) {
            return [...$fallback, 'campaigns' => $this->campaigns->all(self::CAPACITY)];
        }
        $homepage = app(HomepageRenderSnapshot::class)->hero();
        $ids = $homepage === null ? [] : collect(range(1, self::CAPACITY))->map(fn (int $position) => $homepage->{"limited_edition_campaign_{$position}_id"})->all();
        $content = $homepage?->limited_edition_managed ? $homepage->only(array_keys(HomepageHero::limitedEditionDefaults())) : [];
        if (! array_filter($ids)) {
            return [...$fallback, ...$content, 'managed' => (bool) $homepage?->limited_edition_managed, 'campaigns' => $this->campaigns->all(self::CAPACITY)];
        }
        $selected = Campaign::query()->with(['approvedRevision', 'currentDraftRevision', 'products.product.currentDraftRevision', 'claims', 'cardMedia.asset'])->whereIn('id', array_filter($ids))->get()->keyBy('id');

        $items = collect(range(1, self::CAPACITY))->map(function (int $position) use ($homepage, $selected): ?array {
            $id = $homepage->{"limited_edition_campaign_{$position}_id"};
            $campaign = $selected->get($id);
            $presented = $campaign === null ? null : $this->campaigns->present($campaign);

            return $presented === null ? null : [...$presented, 'position' => $position];
        })->filter()->values()->all();

        return [...$fallback, ...$content, 'managed' => (bool) $homepage->limited_edition_managed, 'campaigns' => $items];
    }
}
