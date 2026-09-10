<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateCollectionNavigationOrder
{
    public function __construct(private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, int $order): Collection
    {
        if ($order < 0 || $order > 999999) {
            throw new InvalidArgumentException('Invalid Collection navigation order.');
        }

        return DB::transaction(function () use ($actor, $collection, $order): Collection {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            if ($locked->archived_at !== null) {
                throw new InvalidArgumentException('Archived Collections are read-only.');
            }
            if ($locked->navigation_order === $order) {
                return $locked;
            }

            $before = $locked->navigation_order;
            $locked->forceFill(['navigation_order' => $order, 'lock_version' => $locked->lock_version + 1])->save();
            $this->audit->handle('collection.navigation_order.updated', $locked, $actor, ['navigation_order' => $before], ['navigation_order' => $order]);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));

            return $locked;
        }, 3);
    }
}
