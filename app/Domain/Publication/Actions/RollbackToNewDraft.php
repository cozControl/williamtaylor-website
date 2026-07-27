<?php

namespace App\Domain\Publication\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Campaign\Models\Campaign;
use App\Domain\Campaign\Models\CampaignRevision;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionRevision;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductRevision;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Publication\Data\RollbackDraftResult;
use App\Domain\SiteContent\Models\SiteContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class RollbackToNewDraft
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(User $actor, Model $resource, Model $source, string $expectedCurrentRevisionId, string $reason): RollbackDraftResult
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH);
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A bounded rollback reason is required.');
        }

        return DB::transaction(function () use ($actor, $resource, $source, $expectedCurrentRevisionId, $reason): RollbackDraftResult {
            [$key, $locked, $revisionClass, $ownerField] = match (true) {
                $resource instanceof Page => ['page', $this->lock(Page::class, $resource), ContentRevision::class, 'resource_id'],
                $resource instanceof SiteContent => ['site_content', $this->lock(SiteContent::class, $resource), ContentRevision::class, 'resource_id'],
                $resource instanceof Product => ['product', $this->lock(Product::class, $resource), ProductRevision::class, 'product_id'],
                $resource instanceof Collection => ['collection', $this->lock(Collection::class, $resource), CollectionRevision::class, 'collection_id'],
                $resource instanceof Campaign => ['campaign', $this->lock(Campaign::class, $resource), CampaignRevision::class, 'campaign_id'],
                default => throw new InvalidArgumentException('Unsupported rollback resource.'),
            };
            if ($locked->getAttribute('archived_at') !== null) {
                throw new InvalidArgumentException('Archived resources cannot be rolled back until restored.');
            }
            if ($source instanceof ContentRevision && $source->resource_type !== $locked::class) {
                throw new InvalidArgumentException('Rollback revision resource type is invalid.');
            }
            if (! $source instanceof $revisionClass || (string) $source->getAttribute($ownerField) !== (string) $locked->getKey() || (string) $locked->getAttribute('current_draft_revision_id') !== $expectedCurrentRevisionId) {
                throw new InvalidArgumentException('Rollback source or stale state is invalid.');
            }
            $attributes = collect($source->getAttributes())->except(['id', 'revision_number', 'checksum', 'created_by', 'created_at', 'source_revision_id'])->all();
            $next = ((int) $revisionClass::query()->where($ownerField, $locked->getKey())->max('revision_number')) + 1;
            $attributes['revision_number'] = $next;
            $attributes['source_revision_id'] = $source->getKey();
            $attributes['created_by'] = $actor->getKey();
            $attributes['created_at'] = now('UTC');
            $attributes['checksum'] = hash('sha256', json_encode(collect($attributes)->except(['created_by', 'created_at', 'source_revision_id'])->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $revision = $revisionClass::query()->create($attributes);
            foreach (MediaUsage::query()->where('owner_type', $source->getMorphClass())->where('owner_identifier', $source->getKey())->get() as $usage) {
                $copy = $usage->replicate();
                $copy->id = (string) Str::ulid();
                $copy->owner_identifier = $revision->getKey();
                $copy->save();
            }
            $updates = ['current_draft_revision_id' => $revision->getKey()];
            if (array_key_exists('lock_version', $locked->getAttributes())) {
                $updates['lock_version'] = ((int) $locked->getAttribute('lock_version')) + 1;
            }
            $locked->forceFill($updates)->save();
            $this->audit->handle('publication.rollback-draft-created', $locked, $actor, ['revision_id' => $expectedCurrentRevisionId], ['revision_id' => $revision->getKey(), 'source_revision_id' => $source->getKey(), 'revision_number' => $next], PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH, $reason);

            return new RollbackDraftResult($key, (string) $locked->getKey(), (string) $revision->getKey(), $next, (string) $source->getKey());
        }, 3);
    }

    /** @param class-string<Model> $class */
    private function lock(string $class, Model $resource): Model
    {
        return $class::query()->whereKey((string) $resource->getKey())->lockForUpdate()->firstOrFail();
    }
}
