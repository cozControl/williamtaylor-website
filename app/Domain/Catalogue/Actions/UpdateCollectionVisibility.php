<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateCollectionVisibility
{
    public function __construct(private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, string $status): Collection
    {
        if (! in_array($status, ['draft', 'ready'], true)) {
            throw new InvalidArgumentException('Unsupported Collection visibility.');
        }

        return DB::transaction(function () use ($actor, $collection, $status): Collection {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            if ($locked->archived_at !== null) {
                throw new InvalidArgumentException('Archived Collections are read-only.');
            }
            if ($locked->catalogue_status === $status) {
                return $locked;
            }

            $before = $locked->catalogue_status;
            $locked->forceFill(['catalogue_status' => $status, 'lock_version' => $locked->lock_version + 1])->save();
            $this->audit->handle('collection.visibility.changed', $locked, $actor, ['status' => $before], ['status' => $status]);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));

            return $locked;
        }, 3);
    }
}
