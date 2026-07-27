<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignProduct;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Catalogue\Support\CollectionOrderingKeys;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArchiveCampaignProduct
{
    public function __construct(private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, CampaignProduct $target, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $campaign, $target, $expected, $reason): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $active = CampaignProduct::query()->active()->where('campaign_id', $c->id)->orderBy('position')->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->targets($c->id))) {
                throw new StaleCampaignState;
            }
            $item = $active->firstWhere('id', $target->id);
            if (! $item) {
                throw new InvalidArgumentException('Active target does not belong to Campaign.');
            }
            $item->forceFill(['archived_at' => now('UTC'), 'archived_by' => $actor->id, 'archive_reason' => $reason, 'active_product_key' => CollectionOrderingKeys::archived($item->id), 'position_key' => CollectionOrderingKeys::archived($item->id)])->save();
            foreach ($active->where('id', '!=', $item->id)->values() as $position => $row) {
                $row->forceFill(['position' => 60000 + $position, 'position_key' => CollectionOrderingKeys::active('tmp', $row->id)])->save();
            }
            foreach ($active->where('id', '!=', $item->id)->values() as $position => $row) {
                $row->forceFill(['position' => $position, 'position_key' => CollectionOrderingKeys::active('position', (string) $position)])->save();
            }
            $c->increment('lock_version');
            $this->audit->handle('campaign.product.archived', $item, $actor, null, ['state' => 'archived'], reason: $reason);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
