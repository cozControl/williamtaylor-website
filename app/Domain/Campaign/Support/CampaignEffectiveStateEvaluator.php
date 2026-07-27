<?php

namespace App\Domain\Campaign\Support;

use App\Domain\Campaign\Models\Campaign;
use Carbon\CarbonImmutable;

final class CampaignEffectiveStateEvaluator
{
    public function __construct(private CampaignReadinessEvaluator $readiness) {}

    public function evaluate(Campaign $campaign, CarbonImmutable $now): string
    {
        if ($campaign->archived_at) {
            return 'archived';
        }if (! $this->readiness->evaluate($campaign)->ready) {
            return $campaign->current_draft_revision_id ? 'not_ready' : 'draft';
        }
        if ($now->lessThan($campaign->starts_at)) {
            return 'scheduled';
        }if ($now->greaterThanOrEqualTo($campaign->ends_at)) {
            return 'ended';
        }

        return 'active';
    }
}
