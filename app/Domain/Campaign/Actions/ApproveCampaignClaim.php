<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignClaim;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignClaimRegistry;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ApproveCampaignClaim
{
    public function __construct(private CampaignClaimRegistry $registry, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, CampaignClaim $claim, string $expected): CampaignClaim
    {
        if (! $actor->can(PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $campaign, $claim, $expected): CampaignClaim {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $item = CampaignClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if (! hash_equals($expected, $this->states->claims($c->id))) {
                throw new StaleCampaignState;
            }
            if ($item->campaign_id !== $c->id || $c->archived_at || $item->archived_at || $item->approval_status !== 'in_review') {
                throw new InvalidArgumentException('Only a submitted claim belonging to an active Campaign may be approved.');
            }
            $normalized = $this->registry->normalize($c->campaign_type, $item->claim_key, $item->normalized_value, $item->evidence_reference, $item->evidence_summary);
            if (! hash_equals($item->value_checksum, $normalized['checksum'])) {
                throw new InvalidArgumentException('Campaign claim checksum is invalid.');
            }
            if (in_array($actor->id, [$item->created_by, $item->last_material_by, $item->submitted_by], true)) {
                throw new AuthorizationException('Campaign claims require an independent approver.');
            }
            $item->forceFill(['approval_status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now('UTC'), 'approved_checksum' => $item->value_checksum])->save();
            $c->increment('lock_version');
            $this->audit->handle('campaign.claim.approved', $item, $actor, ['state' => 'in_review'], ['state' => 'approved', 'checksum' => $item->approved_checksum], permission: PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));

            return $item;
        }, 3);
    }
}
