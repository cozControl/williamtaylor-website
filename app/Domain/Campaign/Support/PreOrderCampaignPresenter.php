<?php

namespace App\Domain\Campaign\Support;

use App\Domain\Campaign\Models\Campaign;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

final class PreOrderCampaignPresenter
{
    public function __construct(
        private CampaignPublicProjection $projection,
    ) {}

    /** @return list<array<string, mixed>> */
    public function all(?int $limit = null): array
    {
        if (! Schema::hasColumns('campaigns', ['campaign_type', 'estimated_delivery_date'])) {
            return [];
        }

        $items = Campaign::query()->where('campaign_type', 'pre_order')->whereNull('archived_at')
            ->with(['approvedRevision', 'currentDraftRevision', 'products.product.currentDraftRevision', 'claims', 'cardMedia.asset'])->orderBy('starts_at')->orderBy('id')->lazy(20)
            ->map(fn (Campaign $campaign) => $this->present($campaign))->filter();

        return array_values(($limit === null ? $items : $items->take($limit))->all());
    }

    /** @return array<string, mixed>|null */
    public function present(Campaign $campaign): ?array
    {
        $base = $this->projection->resolve($campaign, 'pre_order');
        if ($base === null || $campaign->estimated_delivery_date === null) {
            return null;
        }

        $now = CarbonImmutable::now('UTC');
        $remaining = max(0, (int) floor($now->diffInSeconds($campaign->ends_at, false)));
        $deliveryDate = CarbonImmutable::parse((string) $campaign->getRawOriginal('estimated_delivery_date'));

        return [
            ...$base,
            'badge' => 'PRE-ORDER',
            'delivery_date' => $deliveryDate->format('Y-m-d'),
            'countdown_target' => $campaign->ends_at->toIso8601String(),
            'remaining' => $this->remaining($remaining),
        ];
    }

    /** @return array{days:int,hours:int,minutes:int,seconds:int} */
    private function remaining(int $seconds): array
    {
        return ['days' => intdiv($seconds, 86400), 'hours' => intdiv($seconds % 86400, 3600), 'minutes' => intdiv($seconds % 3600, 60), 'seconds' => $seconds % 60];
    }
}
