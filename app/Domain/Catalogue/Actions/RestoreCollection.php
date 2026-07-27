<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Domain\Catalogue\Support\CollectionTypeRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class RestoreCollection
{
    public function __construct(private CollectionTypeRegistry $types, private CollectionStateFingerprint $states, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, string $expected): void
    {
        DB::transaction(function () use ($actor, $collection, $expected): void {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            if (! hash_equals($expected, $this->states->identity($locked))) {
                throw new StaleCatalogueState;
            }
            $this->types->get($locked->collection_type);
            if (! $locked->archived_at) {
                return;
            }
            $locked->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null, 'catalogue_status' => 'draft', 'lock_version' => $locked->lock_version + 1])->save();
            $this->audit->handle('collection.restored', $locked, $actor, ['state' => 'archived'], ['state' => 'draft']);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));
        }, 3);
    }
}
