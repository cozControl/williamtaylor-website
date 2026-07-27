<?php

namespace App\Domain\SiteContent\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class EnsureSiteContent
{
    public function __construct(private SiteContentTypeRegistry $types, private RecordAuditEvent $audit) {}

    public function handle(User $actor, string $type, ?string $key = null, ?string $title = null, string $locale = 'en'): SiteContent
    {
        $definition = $this->types->get($type);
        if ($locale !== 'en') {
            throw new InvalidArgumentException('Only the approved English Site Content locale is available.');
        }
        if ($definition->singleton) {
            $key = $type;
        } elseif ($key === null || trim($key) === '') {
            $key = (string) Str::ulid();
        }
        $title = trim((string) $title) ?: $definition->label;

        return DB::transaction(function () use ($actor, $type, $key, $title, $locale, $definition): SiteContent {
            $content = SiteContent::query()->firstOrCreate(
                ['type' => $type, 'key' => $key, 'locale' => $locale],
                ['title' => $title, 'created_by' => $actor->getKey(), 'updated_by' => $actor->getKey()],
            );
            if ($content->current_draft_revision_id === null) {
                $payload = $definition->validate($definition->defaults());
                $revision = ContentRevision::query()->create([
                    'resource_type' => SiteContent::class,
                    'resource_id' => $content->getKey(),
                    'revision_number' => 1,
                    'schema_version' => 2,
                    'payload' => $payload,
                    'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                    'sanitizer_version' => 'not-applicable',
                    'change_summary' => 'Initial '.$definition->label.' draft',
                    'created_by' => $actor->getKey(),
                    'created_at' => now('UTC'),
                ]);
                $content->forceFill(['current_draft_revision_id' => $revision->getKey()])->save();
                $this->audit->handle('site-content.resource.created', $content, $actor, null, [
                    'type' => $type, 'locale' => $locale, 'revision_id' => $revision->getKey(),
                ], $definition->permission($definition->singleton ? 'edit' : 'create'));
            }

            return $content->fresh(['currentDraftRevision', 'publicationState']);
        }, 3);
    }
}
