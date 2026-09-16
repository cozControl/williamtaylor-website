<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Support\PreOrderCampaignPresenter;
use App\Domain\Homepage\Models\HomepageHero;

final class HomepageFutureStylePresenter
{
    public function __construct(private PreOrderCampaignPresenter $campaigns) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $fallback = [...HomepageHero::futureStyleDefaults(), 'managed' => false, 'campaigns' => []];
        if (! app(HomepageRenderSnapshot::class)->hasColumns(['future_style_managed', 'future_style_campaign_2_id'])) {
            return [...$fallback, 'campaigns' => $this->campaigns->all(2)];
        }
        $homepage = app(HomepageRenderSnapshot::class)->hero();
        $ids = $homepage === null ? [] : [$homepage->future_style_campaign_1_id, $homepage->future_style_campaign_2_id];
        $content = $homepage?->future_style_managed ? $homepage->only(array_keys(HomepageHero::futureStyleDefaults())) : [];
        if (! array_filter($ids)) {
            return [...$fallback, ...$content, 'managed' => (bool) $homepage?->future_style_managed, 'campaigns' => $this->campaigns->all(2)];
        }
        $selected = Campaign::query()->with(['approvedRevision', 'currentDraftRevision', 'products.product.currentDraftRevision', 'claims', 'cardMedia.asset'])->whereIn('id', array_filter($ids))->get()->keyBy('id');

        // Resolve only persisted slot IDs. Ineligible or missing selections are omitted,
        // never replaced with discovered campaigns or the static storefront cards.
        $items = collect([1, 2])->map(function (int $position) use ($homepage, $selected): ?array {
            $id = $homepage->{"future_style_campaign_{$position}_id"};
            $campaign = $selected->get($id);
            $presented = $campaign === null ? null : $this->campaigns->present($campaign);

            return $presented === null ? null : [...$presented, 'position' => $position];
        })->filter()->values()->all();

        return [...$fallback, ...$content, 'managed' => (bool) $homepage->future_style_managed, 'campaigns' => $items];
    }
}
