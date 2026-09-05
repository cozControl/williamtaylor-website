<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionProduct;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\CollectionOrderingKeys;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReorderCollectionProducts
{
    public function __construct(private CollectionStateFingerprint $states, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    /**
     * @param  list<string>  $orderedIds
     * @param  list<int>|null  $positions
     */
    public function handle(User $actor, Collection $collection, string $expected, array $orderedIds, ?array $positions = null): void
    {
        DB::transaction(function () use ($actor, $collection, $expected, $orderedIds, $positions): void {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            $members = CollectionProduct::query()->active()->where('collection_id', $locked->id)->orderBy('position')->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->memberships($locked->id))) {
                throw new StaleCatalogueState;
            }
            $nextPositions = $positions ?? array_keys($orderedIds);
            if ($locked->archived_at || count($orderedIds) !== count(array_unique($orderedIds)) || count($nextPositions) !== count($orderedIds) || count($nextPositions) !== count(array_unique($nextPositions)) || $members->pluck('id')->sort()->values()->all() !== collect($orderedIds)->sort()->values()->all()) {
                throw new InvalidArgumentException('A complete duplicate-free order for an active Collection is required.');
            }
            if ($members->pluck('id')->all() === $orderedIds && $members->pluck('position')->all() === $nextPositions) {
                return;
            }
            foreach ($orderedIds as $position => $id) {
                CollectionProduct::query()->whereKey($id)->update(['position' => 60000 + $position, 'position_key' => CollectionOrderingKeys::active('temporary', $id)]);
            }
            foreach ($orderedIds as $index => $id) {
                $position = $nextPositions[$index];
                CollectionProduct::query()->whereKey($id)->update(['position' => $position, 'position_key' => CollectionOrderingKeys::active('position', (string) $position)]);
            }
            $locked->increment('lock_version');
            $this->audit->handle('collection.products.reordered', $locked, $actor, null, ['membership_ids' => $orderedIds, 'positions' => $nextPositions]);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));
        }, 3);
    }
}
