<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\ArchiveReason;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ArchiveCollection
{
    public function __construct(private CollectionStateFingerprint $states, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, string $expected, string $reason): void
    {
        $reason = ArchiveReason::normalize($reason);
        DB::transaction(function () use ($actor, $collection, $expected, $reason): void {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            if (! hash_equals($expected, $this->states->identity($locked))) {
                throw new StaleCatalogueState;
            }
            if ($locked->archived_at) {
                return;
            }
            $locked->forceFill(['archived_at' => now('UTC'), 'archived_by' => $actor->id, 'archive_reason' => $reason, 'catalogue_status' => 'draft', 'lock_version' => $locked->lock_version + 1])->save();
            $this->audit->handle('collection.archived', $locked, $actor, null, ['state' => 'archived'], reason: $reason);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));
        }, 3);
    }
}
