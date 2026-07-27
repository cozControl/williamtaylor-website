<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignClaim;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignClaimRegistry;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateCampaignClaim
{
    public function __construct(private CampaignClaimRegistry $registry, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, string $expected, string $key, string $value, string $reference, string $summary): CampaignClaim
    {
        return DB::transaction(function () use ($actor, $campaign, $expected, $key, $value, $reference, $summary): CampaignClaim {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            CampaignClaim::query()->where('campaign_id', $c->id)->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->claims($c->id))) {
                throw new StaleCampaignState;
            }$n = $this->registry->normalize($c->campaign_type, $key, $value, $reference, $summary);
            $claim = CampaignClaim::query()->create(['campaign_id' => $c->id, 'claim_key' => $key, 'normalized_value' => $n['value'], 'value_checksum' => $n['checksum'], 'evidence_reference' => $n['evidence_reference'], 'evidence_summary' => $n['evidence_summary'], 'approval_status' => 'draft', 'created_by' => $actor->id, 'last_material_by' => $actor->id]);
            $c->increment('lock_version');
            $this->audit->handle('campaign.claim.created', $claim, $actor, null, ['claim_key' => $key, 'checksum' => $n['checksum'], 'state' => 'draft']);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));

            return $claim;
        }, 3);
    }
}
