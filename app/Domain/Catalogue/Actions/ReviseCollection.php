<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Exceptions\StaleCatalogueState;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionRevision;
use App\Domain\Catalogue\Services\CollectionConfigurationCache;
use App\Domain\Catalogue\Support\CollectionContentSchema;
use App\Domain\Catalogue\Support\CollectionStateFingerprint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReviseCollection
{
    public function __construct(private CollectionContentSchema $content, private CollectionStateFingerprint $states, private CollectionConfigurationCache $cache, private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $content */
    public function handle(User $actor, Collection $collection, string $expected, array $content, ?string $note = null): CollectionRevision
    {
        $normalized = $this->content->normalize($content);

        return DB::transaction(function () use ($actor, $collection, $expected, $normalized, $note): CollectionRevision {
            $locked = Collection::query()->lockForUpdate()->findOrFail($collection->id);
            if (! hash_equals($expected, $this->states->identity($locked))) {
                throw new StaleCatalogueState;
            }
            if ($locked->archived_at) {
                throw new InvalidArgumentException('Archived Collections are read-only.');
            }
            $checksum = $this->content->checksum($normalized);
            if ($locked->currentDraftRevision?->checksum === $checksum) {
                throw new InvalidArgumentException('An identical Collection revision already exists.');
            }
            $revision = CollectionRevision::query()->create([...$normalized, 'collection_id' => $locked->id, 'revision_number' => (int) CollectionRevision::query()->where('collection_id', $locked->id)->max('revision_number') + 1, 'checksum' => $checksum, 'revision_note' => ($trimmed = trim((string) $note)) === '' ? null : mb_substr($trimmed, 0, 500), 'created_by' => $actor->id, 'created_at' => now('UTC')]);
            $locked->forceFill(['current_draft_revision_id' => $revision->id, 'catalogue_status' => 'draft', 'lock_version' => $locked->lock_version + 1])->save();
            $this->audit->handle('collection.revised', $locked, $actor, null, ['revision_number' => $revision->revision_number, 'checksum' => $checksum]);
            DB::afterCommit(fn () => $this->cache->invalidate($locked->id));

            return $revision;
        }, 3);
    }
}
