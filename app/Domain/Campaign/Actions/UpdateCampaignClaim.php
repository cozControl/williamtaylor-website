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
use InvalidArgumentException;

final class UpdateCampaignClaim
{
    public function __construct(private CampaignClaimRegistry $registry, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, CampaignClaim $claim, string $expected, string $value, string $reference, string $summary): CampaignClaim
    {
        return DB::transaction(function () use ($actor, $campaign, $claim, $expected, $value, $reference, $summary): CampaignClaim {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $item = CampaignClaim::query()->lockForUpdate()->findOrFail($claim->id);
            if (! hash_equals($expected, $this->states->claims($c->id))) {
                throw new StaleCampaignState;
            }if ($item->campaign_id !== $c->id || $item->archived_at) {
                throw new InvalidArgumentException('Claim does not belong to active Campaign.');
            }$n = $this->registry->normalize($c->campaign_type, $item->claim_key, $value, $reference, $summary);
            $before = $item->value_checksum;
            $wasApproved = $item->approval_status === 'approved';
            $item->forceFill(['normalized_value' => $n['value'], 'value_checksum' => $n['checksum'], 'evidence_reference' => $n['evidence_reference'], 'evidence_summary' => $n['evidence_summary'], 'approval_status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'approved_checksum' => null, 'last_material_by' => $actor->id])->save();
            $c->increment('lock_version');
            if ($wasApproved && $before !== $n['checksum']) {
                $this->audit->handle('campaign.claim.approval-invalidated', $item, $actor, ['checksum' => $before], ['checksum' => $n['checksum'], 'state' => 'draft']);
            }
            $this->audit->handle('campaign.claim.updated', $item, $actor, ['checksum' => $before], ['checksum' => $n['checksum'], 'state' => 'draft']);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));

            return $item;
        }, 3);
    }
}
