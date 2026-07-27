<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Catalogue\Support\CollectionOrderingKeys;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ArchiveCollectionProduct
{
    public function __construct(private CollectionStateFingerprint $states, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, CollectionProduct $membership, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $collection, $membership, $expected, $reason): void {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            $members = CollectionProduct::query()->active()->where('collection_id', $locked->id)->orderBy('position')->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->memberships($locked->id))) {
                throw new StaleCatalogueState;
            }
            $item = $members->firstWhere('id', $membership->id);
            if (! $item) {
                throw new InvalidArgumentException('Active membership does not belong to this Collection.');
            }
            $item->forceFill(['archived_at' => now('UTC'), 'archived_by' => $actor->id, 'archive_reason' => $reason, 'active_product_key' => CollectionOrderingKeys::archived($item->id), 'position_key' => CollectionOrderingKeys::archived($item->id)])->save();
            foreach ($members->where('id', '!=', $item->id)->values() as $position => $active) {
                $active->forceFill(['position' => 60000 + $position, 'position_key' => CollectionOrderingKeys::active('temporary', $active->id)])->save();
            }
            foreach ($members->where('id', '!=', $item->id)->values() as $position => $active) {
                $active->forceFill(['position' => $position, 'position_key' => CollectionOrderingKeys::active('position', (string) $position)])->save();
            }
            $locked->increment('lock_version');
            $this->audit->handle('collection.product.archived', $item, $actor, null, ['state' => 'archived'], reason: $reason);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));
        }, 3);
    }
}
