<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignClaim;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class WithdrawCampaignClaimApproval
{
    public function __construct(private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, CampaignClaim $claim, string $expected, string $reason): void
    {
        if (! $actor->can(PermissionRegistry::CAMPAIGN_CLAIMS_WITHDRAW_APPROVAL)) {
            throw new AuthorizationException;
        }
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $campaign, $claim, $expected, $reason): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $item = CampaignClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if (! hash_equals($expected, $this->states->claims($c->id))) {
                throw new StaleCampaignState;
            }
            if ($item->campaign_id !== $c->id || $item->approval_status !== 'approved') {
                throw new \InvalidArgumentException('Only an approved claim may be withdrawn.');
            }
            $item->forceFill(['approval_status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'approved_checksum' => null])->save();
            $c->increment('lock_version');
            $this->audit->handle('campaign.claim.approval-withdrawn', $item, $actor, ['state' => 'approved'], ['state' => 'draft'], permission: PermissionRegistry::CAMPAIGN_CLAIMS_WITHDRAW_APPROVAL, reason: $reason);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
