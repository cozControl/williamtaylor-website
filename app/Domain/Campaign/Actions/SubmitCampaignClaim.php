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

final class SubmitCampaignClaim
{
    public function __construct(private CampaignClaimRegistry $registry, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, CampaignClaim $claim, string $expected): void
    {
        DB::transaction(function () use ($actor, $campaign, $claim, $expected): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $item = CampaignClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if (! hash_equals($expected, $this->states->claims($c->id))) {
                throw new StaleCampaignState;
            }$this->registry->normalize($c->campaign_type, $item->claim_key, $item->normalized_value, $item->evidence_reference, $item->evidence_summary);
            $item->forceFill(['approval_status' => 'in_review', 'submitted_by' => $actor->id, 'submitted_at' => now('UTC')])->save();
            $c->increment('lock_version');
            $this->audit->handle('campaign.claim.submitted', $item, $actor, null, ['state' => 'in_review']);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
