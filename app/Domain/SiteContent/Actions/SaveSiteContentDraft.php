<?php

namespace App\Domain\SiteContent\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Support\SiteContentSchema;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SaveSiteContentDraft
{
    public function __construct(private SiteContentSchema $schema, private SiteContentTypeRegistry $types, private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $payload */
    public function handle(User $actor, SiteContent $content, string $expectedRevisionId, array $payload, string $summary): ContentRevision
    {
        $permission = $this->types->get($content->type)->permission('edit');
        Gate::forUser($actor)->authorize($permission);
        $summary = trim($summary);
        if ($summary === '') {
            throw new InvalidArgumentException('A change summary is required.');
        }
        $validated = $this->schema->validate($content->type, $payload);

        return DB::transaction(function () use ($actor, $content, $expectedRevisionId, $validated, $summary, $permission): ContentRevision {
            $locked = SiteContent::query()->whereKey($content->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->current_draft_revision_id !== $expectedRevisionId) {
                throw new InvalidArgumentException('Site Content has a newer draft revision.');
            }
            $next = ((int) $locked->revisions()->max('revision_number')) + 1;
            $revision = ContentRevision::query()->create([
                'resource_type' => SiteContent::class,
                'resource_id' => $locked->getKey(),
                'revision_number' => $next,
                'schema_version' => SiteContentSchema::VERSION,
                'payload' => $validated,
                'checksum' => hash('sha256', json_encode($validated, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'sanitizer_version' => 'not-applicable',
                'change_summary' => $summary,
                'created_by' => $actor->getKey(),
                'created_at' => now('UTC'),
            ]);
            $this->attachMediaUsages($locked, $revision, $validated);
            $locked->forceFill([
                'current_draft_revision_id' => $revision->getKey(),
                'updated_by' => $actor->getKey(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->audit->handle('site-content.draft-saved', $locked, $actor, null, [
                'revision_id' => $revision->getKey(),
                'revision_number' => $next,
                'checksum' => substr($revision->checksum, 0, 12),
            ], $permission, $summary);

            return $revision;
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    private function attachMediaUsages(SiteContent $content, ContentRevision $revision, array $payload): void
    {
        if ($content->type !== SiteContentTypeRegistry::SITE_PROFILE) {
            return;
        }
        $roles = [
            'header_logo' => data_get($payload, 'brand.header_logo_id'),
            'footer_logo' => data_get($payload, 'brand.footer_logo_id'),
            'footer_image' => data_get($payload, 'footer.footer_image_id'),
        ];
        foreach ($roles as $role => $assetId) {
            if (! is_string($assetId) || $assetId === '') {
                continue;
            }
            $asset = MediaAsset::query()->whereKey($assetId)->firstOrFail();
            if ($asset->state !== MediaAssetState::Ready || $asset->archived_at !== null) {
                throw new InvalidArgumentException('Only ready, active Media assets can be selected.');
            }
            MediaUsage::query()->create([
                'id' => (string) Str::ulid(),
                'media_asset_id' => $asset->getKey(),
                'owner_type' => ContentRevision::class,
                'owner_identifier' => $revision->getKey(),
                'field_role' => $role,
                'locale' => $content->locale,
                'alt_text_override' => null,
                'decorative_override' => $role === 'footer_image',
                'sort_order' => 0,
            ]);
        }
    }
}
