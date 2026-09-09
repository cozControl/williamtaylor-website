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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AssignProductToCollection
{
    public function __construct(private CollectionTypeRegistry $types, private CollectionStateFingerprint $states, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, Product $product, string $expected): CollectionProduct
    {
        try {
            return DB::transaction(function () use ($actor, $collection, $product, $expected): CollectionProduct {
                $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
                $members = CollectionProduct::query()->active()->where('collection_id', $locked->id)->orderBy('position')->lockForUpdate()->get();
                if (! hash_equals($expected, $this->states->memberships($locked->id))) {
                    throw new StaleCatalogueState;
                }
                $definition = $this->types->get($locked->collection_type);
                $target = Product::query()->lockForUpdate()->findOrFail($product->id);
                if (! $definition['product_membership'] || $locked->archived_at || $target->archived_at) {
                    throw new InvalidArgumentException('Collection and Product must support an active membership.');
                }
                if ($members->contains('product_id', $target->id)) {
                    throw new InvalidArgumentException('Product is already assigned to this Collection.');
                }
                $maximumPosition = $members->max('position');
                $position = $maximumPosition === null ? 0 : ((int) $maximumPosition) + 1;
                $membership = CollectionProduct::query()->create(['collection_id' => $locked->id, 'product_id' => $target->id, 'position' => $position, 'active_product_key' => CollectionOrderingKeys::active('product', $target->id), 'position_key' => CollectionOrderingKeys::active('position', (string) $position), 'created_by' => $actor->id]);
                $locked->increment('lock_version');
                $this->audit->handle('collection.product.assigned', $membership, $actor, null, ['collection_id' => $locked->id, 'product_id' => $target->id, 'position' => $position]);
                DB::afterCommit(fn () => $this->cache->invalidate($locked->id));

                return $membership;
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new InvalidArgumentException('Collection membership conflicts with active state.', previous: $exception);
            }
            throw $exception;
        }
    }
}
