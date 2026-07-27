<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Models\CampaignClaim;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ArchiveCampaignClaim
{
    public function __construct(private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, CampaignClaim $claim, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $claim, $reason): void {
            $item = CampaignClaim::query()->lockForUpdate()->findOrFail($claim->id);
            $item->forceFill(['archived_at' => now('UTC'), 'archived_by' => $actor->id, 'archive_reason' => $reason, 'approval_status' => 'draft', 'approved_by' => null, 'approved_at' => null, 'approved_checksum' => null])->save();
            $this->audit->handle('campaign.claim.archived', $item, $actor, null, ['state' => 'archived'], reason: $reason);
            DB::afterCommit(fn () => $this->cache->invalidate($item->campaign_id));
        }, 3);
    }
}
