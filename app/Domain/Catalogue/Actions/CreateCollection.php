<?php

namespace App\Domain\Catalogue\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionRevision;
use App\Domain\Catalogue\Support\CollectionContentSchema;
use App\Domain\Catalogue\Support\CollectionSlug;
use App\Domain\Catalogue\Support\CollectionTypeRegistry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateCollection
{
    public function __construct(private CollectionTypeRegistry $types, private CollectionContentSchema $content, private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $content */
    public function handle(User $actor, string $slug, array $content, string $type = CollectionTypeRegistry::CURATED, ?string $note = null): Collection
    {
        $this->types->get($type);
        $slug = CollectionSlug::normalize($slug);
        $normalized = $this->content->normalize($content);

        try {
            return DB::transaction(function () use ($actor, $slug, $type, $normalized, $note): Collection {
                $collection = Collection::query()->create(['collection_type' => $type, 'slug' => $slug, 'catalogue_status' => 'draft', 'lock_version' => 0, 'created_by' => $actor->id]);
                $revision = CollectionRevision::query()->create([...$normalized, 'collection_id' => $collection->id, 'revision_number' => 1, 'checksum' => $this->content->checksum($normalized), 'revision_note' => $this->note($note), 'created_by' => $actor->id, 'created_at' => now('UTC')]);
                $collection->forceFill(['current_draft_revision_id' => $revision->id])->save();
                $this->audit->handle('collection.created', $collection, $actor, null, ['type' => $type, 'slug' => $slug, 'revision_number' => 1, 'checksum' => $revision->checksum]);

                return $collection->refresh();
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000') {
                throw new InvalidArgumentException('Collection slug is already in use.', previous: $exception);
            }
            throw $exception;
        }
    }

    private function note(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, 500);
    }
}
