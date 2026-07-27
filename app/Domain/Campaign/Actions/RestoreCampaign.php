<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Campaign\Support\CampaignTypeRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RestoreCampaign
{
    public function __construct(private CampaignTypeRegistry $types, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, string $expected): void
    {
        DB::transaction(function () use ($actor, $campaign, $expected): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (! hash_equals($expected, $this->states->identity($c))) {
                throw new StaleCampaignState;
            }$this->types->get($c->campaign_type);
            $c->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null, 'lifecycle_status' => 'draft', 'approved_revision_id' => null, 'lock_version' => $c->lock_version + 1])->save();
            $this->audit->handle('campaign.restored', $c, $actor, ['state' => 'archived'], ['state' => 'draft']);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
