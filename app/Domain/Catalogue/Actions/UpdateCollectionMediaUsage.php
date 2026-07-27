<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\CollectionMediaAccessibility;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateCollectionMediaUsage
{
    public function __construct(private CollectionStateFingerprint $states, private CollectionMediaAccessibility $accessibility, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, MediaUsage $usage, string $expected, ?string $alt, ?bool $decorative = null): MediaUsage
    {
        return DB::transaction(function () use ($actor, $collection, $usage, $expected, $alt, $decorative): MediaUsage {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            $item = MediaUsage::query()->with('asset')->lockForUpdate()->findOrFail($usage->id);
            if (! hash_equals($expected, $this->states->media($locked->id))) {
                throw new StaleCatalogueState;
            }
            if ($item->owner_type !== Collection::class || $item->owner_identifier !== $locked->id || ! $item->asset || $locked->archived_at) {
                throw new InvalidArgumentException('Media usage does not belong to an active Collection.');
            }
            $this->accessibility->assertAssignable($item->asset, $alt, $decorative);
            $before = ['alt_text_override' => $item->alt_text_override];
            $item->forceFill(['alt_text_override' => $alt === null ? null : trim($alt), 'decorative_override' => false])->save();
            $locked->increment('lock_version');
            $this->audit->handle('collection.media.updated', $locked, $actor, $before, ['usage_id' => $item->id, 'has_alt_override' => $alt !== null]);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));

            return $item;
        }, 3);
    }
}
