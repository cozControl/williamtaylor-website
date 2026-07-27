<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RemoveCollectionMedia
{
    public function __construct(private CollectionStateFingerprint $states, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, MediaUsage $usage, string $expected): void
    {
        DB::transaction(function () use ($actor, $collection, $usage, $expected): void {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            $item = MediaUsage::query()->lockForUpdate()->findOrFail($usage->id);
            if (! hash_equals($expected, $this->states->media($locked->id))) {
                throw new StaleCatalogueState;
            }
            if ($item->owner_type !== Collection::class || $item->owner_identifier !== $locked->id || $locked->archived_at) {
                throw new InvalidArgumentException('Media usage does not belong to an active Collection.');
            }
            $summary = ['usage_id' => $item->id, 'asset_id' => $item->media_asset_id, 'role' => $item->field_role];
            $item->delete();
            $locked->increment('lock_version');
            $this->audit->handle('collection.media.removed', $locked, $actor, $summary, null);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));
        }, 3);
    }
}
