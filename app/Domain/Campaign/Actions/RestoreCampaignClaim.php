<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignClaim;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignClaimRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RestoreCampaignClaim
{
    public function __construct(private CampaignClaimRegistry $registry, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, CampaignClaim $claim): void
    {
        DB::transaction(function () use ($actor, $campaign, $claim): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $item = CampaignClaim::query()->lockForUpdate()->findOrFail($claim->id);
            $this->registry->get($c->campaign_type, $item->claim_key);
            $item->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null, 'approval_status' => 'draft'])->save();
            $this->audit->handle('campaign.claim.restored', $item, $actor, ['state' => 'archived'], ['state' => 'draft']);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
