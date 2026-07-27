<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignProduct;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Catalogue\Support\CollectionOrderingKeys;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReorderCampaignProducts
{
    public function __construct(private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    /** @param list<string> $ids */
    public function handle(User $actor, Campaign $campaign, string $expected, array $ids): void
    {
        DB::transaction(function () use ($actor, $campaign, $expected, $ids): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $items = CampaignProduct::query()->active()->where('campaign_id', $c->id)->orderBy('position')->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->targets($c->id))) {
                throw new StaleCampaignState;
            }if (count($ids) !== count(array_unique($ids)) || $items->pluck('id')->sort()->values()->all() !== collect($ids)->sort()->values()->all()) {
                throw new InvalidArgumentException('Complete Campaign Product order required.');
            }if ($items->pluck('id')->all() === $ids) {
                return;
            }foreach ($ids as $p => $id) {
                CampaignProduct::query()->whereKey($id)->update(['position' => 60000 + $p, 'position_key' => CollectionOrderingKeys::active('tmp', $id)]);
            }foreach ($ids as $p => $id) {
                CampaignProduct::query()->whereKey($id)->update(['position' => $p, 'position_key' => CollectionOrderingKeys::active('position', (string) $p)]);
            }$c->increment('lock_version');
            $this->audit->handle('campaign.products.reordered', $c, $actor, null, ['target_ids' => $ids]);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
