<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ArchiveCampaign
{
    public function __construct(private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $campaign, $expected, $reason): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (! hash_equals($expected, $this->states->identity($c))) {
                throw new StaleCampaignState;
            }$c->forceFill(['archived_at' => now('UTC'), 'archived_by' => $actor->id, 'archive_reason' => $reason, 'lifecycle_status' => 'draft', 'lock_version' => $c->lock_version + 1])->save();
            $this->audit->handle('campaign.archived', $c, $actor, null, ['state' => 'archived'], reason: $reason);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
