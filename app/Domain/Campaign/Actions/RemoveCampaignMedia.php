<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RemoveCampaignMedia
{
    public function __construct(private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, MediaUsage $usage, string $expected): void
    {
        DB::transaction(function () use ($actor, $campaign, $usage, $expected): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $item = MediaUsage::query()->lockForUpdate()->findOrFail($usage->id);
            if (! hash_equals($expected, $this->states->media($c->id))) {
                throw new StaleCampaignState;
            }
            if ($item->owner_type !== Campaign::class || $item->owner_identifier !== $c->id) {
                throw new InvalidArgumentException('Campaign media usage ownership mismatch.');
            }
            $before = ['usage_id' => $item->id, 'asset_id' => $item->media_asset_id, 'role' => $item->field_role];
            $item->delete();
            $c->increment('lock_version');
            $this->audit->handle('campaign.media.removed', $c, $actor, $before, null);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
