<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\CollectionSlug;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ChangeCollectionSlug
{
    public function __construct(private CollectionStateFingerprint $states, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Collection $collection, string $expected, string $slug): Collection
    {
        $slug = CollectionSlug::normalize($slug);
        try {
            return DB::transaction(function () use ($actor, $collection, $expected, $slug): Collection {
                $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
                if (! hash_equals($expected, $this->states->identity($locked))) {
                    throw new StaleCatalogueState;
                }
                if ($locked->archived_at) {
                    throw new InvalidArgumentException('Archived Collections are read-only.');
                }
                if ($locked->slug === $slug) {
                    return $locked;
                }
                $old = $locked->slug;
                $locked->forceFill(['slug' => $slug, 'catalogue_status' => 'draft', 'lock_version' => $locked->lock_version + 1])->save();
                $this->audit->handle('collection.slug.changed', $locked, $actor, ['slug' => $old], ['slug' => $slug]);
                DB::afterCommit(fn () => $this->cache->invalidate($locked->id));

                return $locked;
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new InvalidArgumentException('Collection slug is already in use.', previous: $exception);
            }
            throw $exception;
        }
    }
}
