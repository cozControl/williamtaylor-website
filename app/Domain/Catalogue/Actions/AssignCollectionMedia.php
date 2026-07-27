<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\CollectionMediaAccessibility;
use App\Domain\Catalogue\Support\CollectionMediaRoleRegistry;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AssignCollectionMedia
{
    public function __construct(private CollectionStateFingerprint $states, private CollectionMediaRoleRegistry $roles, private CollectionMediaAccessibility $accessibility, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, MediaAsset $asset, string $expected, string $role, ?string $alt = null, ?bool $decorative = null): MediaUsage
    {
        $this->roles->get(Collection::class, $role);

        return DB::transaction(function () use ($actor, $collection, $asset, $expected, $role, $alt, $decorative): MediaUsage {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            $usages = MediaUsage::query()->where('owner_type', Collection::class)->where('owner_identifier', $locked->id)->lockForUpdate()->get();
            if (! hash_equals($expected, $this->states->media($locked->id))) {
                throw new StaleCatalogueState;
            }
            $media = MediaAsset::query()->lockForUpdate()->findOrFail($asset->id);
            $this->accessibility->assertAssignable($media, $alt, $decorative);
            if ($locked->archived_at || $usages->contains('media_asset_id', $media->id) || $usages->contains('field_role', $role)) {
                throw new InvalidArgumentException('Collection media assignment conflicts with active state.');
            }
            $usage = MediaUsage::query()->create(['id' => (string) Str::ulid(), 'media_asset_id' => $media->id, 'owner_type' => Collection::class, 'owner_identifier' => $locked->id, 'field_role' => $role, 'locale' => null, 'alt_text_override' => $alt === null ? null : trim($alt), 'decorative_override' => false, 'sort_order' => 0]);
            $locked->increment('lock_version');
            $this->audit->handle('collection.media.assigned', $locked, $actor, null, ['usage_id' => $usage->id, 'asset_id' => $media->id, 'role' => $role, 'has_alt_override' => $alt !== null]);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));

            return $usage;
        }, 3);
    }
}
