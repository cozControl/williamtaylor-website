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

final class AssignCampaignProduct
{
    public function __construct(private CampaignTypeRegistry $types, private CampaignStateFingerprint $states, private CampaignConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Campaign $campaign, Product $product, string $expected): CampaignProduct
    {
        return DB::transaction(function () use ($actor, $campaign, $product, $expected): CampaignProduct {
            $c = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            $items = CampaignProduct::query()->active()->where('campaign_id', $c->id)->orderBy('position')->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->targets($c->id))) {
                throw new StaleCampaignState;
            }$def = $this->types->get($c->campaign_type);
            $p = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($c->archived_at || $p->archived_at || $items->contains('product_id', $p->id) || $items->count() >= $def['maximum']) {
                throw new InvalidArgumentException('Campaign Product cannot be assigned.');
            }
            $pos = $items->count();
            $item = CampaignProduct::query()->create(['campaign_id' => $c->id, 'product_id' => $p->id, 'position' => $pos, 'active_product_key' => CollectionOrderingKeys::active('product', $p->id), 'position_key' => CollectionOrderingKeys::active('position', (string) $pos), 'created_by' => $actor->id]);
            $c->increment('lock_version');
            $this->audit->handle('campaign.product.assigned', $item, $actor, null, ['campaign_id' => $c->id, 'product_id' => $p->id, 'position' => $pos]);
            DB::afterCommit(fn () => $this->cache->invalidate($c->id));

            return $item;
        }, 3);
    }
}
