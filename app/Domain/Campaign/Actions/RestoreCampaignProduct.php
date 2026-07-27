<?php

namespace App\Domain\Campaign\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Exceptions\StaleCampaignState;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignProduct;
use App\Domain\Campaign\Services\CampaignConfigurationCache;
use App\Domain\Campaign\Support\CampaignStateFingerprint;
use App\Domain\Campaign\Support\CampaignTypeRegistry;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Support\CollectionOrderingKeys;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RestoreCampaignProduct
{
    public function __construct(private CampaignTypeRegistry $types, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, CampaignProduct $target, string $expected): void
    {
        DB::transaction(function () use ($actor, $campaign, $target, $expected): void {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $active = CampaignProduct::query()->active()->where('campaign_id', $c->id)->orderBy('position')->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->targets($c->id))) {
                throw new StaleCampaignState;
            }
            $item = CampaignProduct::query()->lockForUpdate()->findOrFail($target->id);
            $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
            $definition = $this->types->get($c->campaign_type);
            if ($item->campaign_id !== $c->id || ! $item->archived_at || $c->archived_at || $product->archived_at || $active->contains('product_id', $product->id) || $active->count() >= $definition['maximum']) {
                throw new InvalidArgumentException('Campaign target cannot be restored.');
            }
            $position = $active->count();
            $item->forceFill(['position' => $position, 'archived_at' => null, 'archived_by' => null, 'archive_reason' => null, 'active_product_key' => CollectionOrderingKeys::active('product', $product->id), 'position_key' => CollectionOrderingKeys::active('position', (string) $position)])->save();
            $c->increment('lock_version');
            $this->audit->handle('campaign.product.restored', $item, $actor, ['state' => 'archived'], ['state' => 'active', 'position' => $position]);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));
        }, 3);
    }
}
