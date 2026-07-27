<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\CollectionOrderingKeys;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Domain\Catalogue\Support\CollectionTypeRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RestoreCollectionProduct
{
    public function __construct(private CollectionTypeRegistry $types, private CollectionStateFingerprint $states, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, CollectionProduct $membership, string $expected): void
    {
        DB::transaction(function () use ($actor, $collection, $membership, $expected): void {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            $active = CollectionProduct::query()->active()->where('collection_id', $locked->id)->orderBy('position')->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->memberships($locked->id))) {
                throw new StaleCatalogueState;
            }
            $item = CollectionProduct::query()->lockForUpdate()->findOrFail($membership->id);
            $product = Product::query()->lockForUpdate()->findOrFail($item->product_id);
            $definition = $this->types->get($locked->collection_type);
            if ($item->collection_id !== $locked->id || ! $item->archived_at || $locked->archived_at || $product->archived_at || ! $definition['product_membership'] || $active->contains('product_id', $product->id)) {
                throw new InvalidArgumentException('Membership cannot be restored to this Collection.');
            }
            $position = $active->count();
            $item->forceFill(['position' => $position, 'archived_at' => null, 'archived_by' => null, 'archive_reason' => null, 'active_product_key' => CollectionOrderingKeys::active('product', $product->id), 'position_key' => CollectionOrderingKeys::active('position', (string) $position)])->save();
            $locked->increment('lock_version');
            $this->audit->handle('collection.product.restored', $item, $actor, ['state' => 'archived'], ['state' => 'active', 'position' => $position]);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));
        }, 3);
    }
}
