<?php

namespace App\Domain\Factory\Services;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Models\SiteContentPublicationState;
use App\Domain\SiteContent\Models\SiteContentPublicationTransition;
use App\Domain\SiteContent\Support\SiteContentSchema;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class FactoryContentInstaller
{
    public function __construct(
        private readonly FactoryManifest $manifest,
        private readonly SiteContentSchema $schema,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @return array{created:int,reused:int,restored:int,revisions:int,transitions:int} */
    public function apply(User $actor, bool $restoreDrift = false): array
    {
        $result = ['created' => 0, 'reused' => 0, 'restored' => 0, 'revisions' => 0, 'transitions' => 0];

        DB::transaction(function () use ($actor, $restoreDrift, &$result): void {
            foreach ($this->manifest->content() as $definition) {
                $payload = $this->schema->validate($definition['type'], $definition['payload']);
                $checksum = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
                $content = SiteContent::query()->where([
                    'type' => $definition['type'], 'key' => $definition['key'], 'locale' => 'en',
                ])->lockForUpdate()->first();
                $created = $content === null;
                if ($created) {
                    $content = SiteContent::query()->create([
                        'type' => $definition['type'], 'key' => $definition['key'], 'locale' => 'en',
                        'title' => $definition['title'], 'created_by' => $actor->getKey(),
                        'updated_by' => $actor->getKey(), 'lock_version' => 1,
                    ]);
                    $result['created']++;
                } else {
                    $publicChecksum = $content->publicationState?->currentPublicRevision?->checksum;
                    if ($publicChecksum === $checksum) {
                        $result['reused']++;

                        continue;
                    }
                    if (! $restoreDrift) {
                        throw new RuntimeException("Factory Site Content [{$definition['key']}] has drift. Use the scoped reset command to restore it.");
                    }
                    $result['restored']++;
                }

                $revisionNumber = ((int) $content->revisions()->max('revision_number')) + 1;
                $revision = ContentRevision::query()->create([
                    'resource_type' => SiteContent::class, 'resource_id' => $content->getKey(),
                    'revision_number' => $revisionNumber, 'schema_version' => SiteContentSchema::VERSION,
                    'payload' => $payload, 'checksum' => $checksum, 'sanitizer_version' => 'not-applicable',
                    'change_summary' => FactoryManifest::VERSION.' baseline', 'created_by' => $actor->getKey(),
                    'created_at' => now('UTC'),
                ]);
                $content->forceFill([
                    'title' => $definition['title'], 'current_draft_revision_id' => $revision->getKey(),
                    'updated_by' => $actor->getKey(), 'archived_at' => null, 'archived_by' => null,
                    'lock_version' => $content->lock_version + ($created ? 0 : 1),
                ])->save();
                $state = SiteContentPublicationState::query()->firstOrCreate(
                    ['site_content_id' => $content->getKey()], ['state_version' => 0]
                );
                $from = $state->current_public_revision_id === null ? 'draft' : 'published';
                foreach ([[$from, 'in_review'], ['in_review', 'approved'], ['approved', 'published']] as [$fromState, $toState]) {
                    SiteContentPublicationTransition::query()->create([
                        'site_content_id' => $content->getKey(), 'publication_state_id' => $state->getKey(),
                        'from_state' => $fromState, 'to_state' => $toState, 'revision_id' => $revision->getKey(),
                        'actor_id' => $actor->getKey(), 'note' => FactoryManifest::VERSION.' baseline transition',
                        'reason' => 'Explicit factory installation', 'correlation_id' => (string) Str::ulid(),
                        'occurred_at' => now('UTC'),
                    ]);
                    $result['transitions']++;
                }
                $state->forceFill([
                    'candidate_revision_id' => null, 'candidate_state' => null,
                    'current_public_revision_id' => $revision->getKey(), 'submitted_by' => $actor->getKey(),
                    'approved_by' => $actor->getKey(), 'approved_at' => now('UTC'),
                    'scheduled_by' => null, 'scheduled_for' => null,
                    'state_version' => $state->state_version + 3, 'last_transition_at' => now('UTC'),
                ])->save();
                $result['revisions']++;
                $this->audit->handle(
                    $created ? 'factory.site-content.installed' : 'factory.site-content.restored',
                    $content, $actor, null,
                    ['factory_version' => FactoryManifest::VERSION, 'manifest_checksum' => $this->manifest->checksum(), 'revision_number' => $revisionNumber, 'checksum' => substr($checksum, 0, 12)],
                    null, 'Explicit factory baseline operation'
                );
            }
        }, 3);

        return $result;
    }
}
