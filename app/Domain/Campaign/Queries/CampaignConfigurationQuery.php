<?php

namespace App\Domain\Campaign\Queries;

use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignEffectiveStateEvaluator;
use App\Domain\Campaign\Support\CampaignReadinessEvaluator;
use Carbon\CarbonImmutable;

final class CampaignConfigurationQuery
{
    public function __construct(private CampaignConfigurationCache $cache, private CampaignReadinessEvaluator $readiness, private CampaignEffectiveStateEvaluator $effective) {}

    /** @return array<string,mixed> */
    public function find(string $id, CarbonImmutable $now): array
    {
        $data = $this->cache->remember($id, function () use ($id): array {
            $c = Campaign::query()->with(['currentDraftRevision', 'approvedRevision', 'products.product', 'claims'])->findOrFail($id);
            $ready = $this->readiness->evaluate($c);

            return ['id' => $c->id, 'type' => $c->campaign_type, 'code' => $c->internal_code, 'revision' => $c->currentDraftRevision?->only(['id', 'revision_number', 'headline', 'summary', 'cta_label', 'checksum']), 'approved_revision_id' => $c->approved_revision_id, 'starts_at' => $c->starts_at?->toISOString(), 'ends_at' => $c->ends_at?->toISOString(), 'timezone' => $c->business_timezone, 'targets' => $c->products->whereNull('archived_at')->map(fn ($x) => ['id' => $x->id, 'product_id' => $x->product_id, 'position' => $x->position])->values()->all(), 'claims' => $c->claims->map(fn ($x) => ['id' => $x->id, 'key' => $x->claim_key, 'checksum' => $x->value_checksum, 'state' => $x->approval_status])->all(), 'readiness' => ['ready' => $ready->ready, 'failures' => $ready->failureCodes], 'archived' => $c->archived_at !== null];
        });
        $campaign = Campaign::query()->findOrFail($id);
        $data['effective_state'] = $this->effective->evaluate($campaign, $now);

        return $data;
    }
}
