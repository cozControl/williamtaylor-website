<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CancelCampaignSchedule
{
    public function __construct(private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, string $expected): void
    {
        DB::transaction(function () use ($actor, $campaign, $expected): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (! hash_equals($expected, $this->states->identity($c))) {
                throw new StaleCampaignState;
            }$before = ['starts_at' => $c->starts_at?->toISOString(), 'ends_at' => $c->ends_at?->toISOString()];
            $c->forceFill(['starts_at' => null, 'ends_at' => null, 'approved_revision_id' => null, 'lock_version' => $c->lock_version + 1])->save();
            $this->audit->handle('campaign.schedule.cancelled', $c, $actor, $before, null);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
