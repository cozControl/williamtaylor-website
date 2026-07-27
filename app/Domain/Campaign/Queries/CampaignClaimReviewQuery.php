<?php

namespace App\Domain\Campaign\Queries;

use App\Domain\Campaign\Models\CampaignClaim;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class CampaignClaimReviewQuery
{
    /** @return array<string, mixed> */
    public function find(User $actor, string $claimId): array
    {
        if (! $actor->can(PermissionRegistry::CAMPAIGN_CLAIMS_REVIEW)) {
            throw new AuthorizationException;
        }
        $claim = CampaignClaim::query()->findOrFail($claimId);

        return $claim->only(['id', 'campaign_id', 'claim_key', 'normalized_value', 'value_checksum', 'evidence_reference', 'evidence_summary', 'approval_status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at']);
    }
}
