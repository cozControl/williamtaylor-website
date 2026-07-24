<?php

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\PageTypeRegistry;
use App\Domain\Content\Support\RevisionPayload;
use App\Domain\Content\Support\SectionRegistry;
use App\Domain\Content\Support\SlugRules;
use App\Domain\Content\Support\TemplateRegistry;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class CreatePageDraft
{
    public function __construct(
        private PageTypeRegistry $types,
        private TemplateRegistry $templates,
        private SectionRegistry $sections,
        private RevisionPayload $payloads,
        private RecordAuditEvent $audit,
    ) {}

    public function handle(User $actor, string $type, string $title, string $slug, string $locale, string $templateKey): Page
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_CREATE);
        $definition = $this->types->get($type);
        $this->templates->get($templateKey);
        if (! in_array($templateKey, $definition['templates'], true) || $locale !== 'en') {
            throw new \InvalidArgumentException('Page type, template, or locale is not approved.');
        }
        $data = Validator::make(compact('title', 'slug', 'locale'), [
            'title' => ['required', 'string', 'max:255'],
            'slug' => SlugRules::for($locale),
            'locale' => ['in:en'],
        ])->validate();

        return DB::transaction(function () use ($actor, $type, $data, $templateKey): Page {
            $page = Page::query()->create([
                'type' => $type,
                'locale' => $data['locale'],
                'title' => $data['title'],
                'slug' => $data['slug'],
                'template_key' => $templateKey,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);
            $section = $this->sections->normalize([
                'key' => (string) Str::ulid(),
                'type' => 'rich_text',
                'schema_version' => 1,
                'data' => ['document' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => []]]]],
            ], $type);
            $payload = $this->payloads->normalize(['page' => ['title' => $data['title']], 'sections' => [$section]]);
            $revision = ContentRevision::query()->create([
                'resource_type' => Page::class,
                'resource_id' => $page->getKey(),
                'revision_number' => 1,
                'schema_version' => 1,
                'payload' => $payload,
                'checksum' => $this->payloads->checksum($payload),
                'sanitizer_version' => '1',
                'change_summary' => 'Initial draft',
                'created_by' => $actor->getKey(),
                'created_at' => now('UTC'),
            ]);
            $page->current_draft_revision_id = $revision->getKey();
            $page->save();
            $this->audit->handle('content.page.created', $page, $actor, null, ['revision_id' => $revision->getKey(), 'revision_number' => 1, 'section_count' => 1], PermissionRegistry::PAGES_CREATE);

            return $page->load('currentDraftRevision');
        }, 3);
    }
}
