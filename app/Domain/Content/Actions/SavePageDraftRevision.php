<?php

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Content\Exceptions\StaleDraftException;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\PageTypeRegistry;
use App\Domain\Content\Support\RevisionPayload;
use App\Domain\Content\Support\SectionRegistry;
use App\Domain\Content\Support\SlugRules;
use App\Domain\Content\Support\TemplateRegistry;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SavePageDraftRevision
{
    public function __construct(
        private PageTypeRegistry $types,
        private TemplateRegistry $templates,
        private SectionRegistry $sections,
        private RevisionPayload $payloads,
        private RecordAuditEvent $audit,
    ) {}

    /** @param list<array<string, mixed>> $sections */
    public function handle(User $actor, Page $page, string $expectedRevisionId, string $title, string $slug, string $templateKey, array $sections, ?string $summary): ContentRevision
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::PAGES_EDIT);

        return DB::transaction(function () use ($actor, $page, $expectedRevisionId, $title, $slug, $templateKey, $sections, $summary): ContentRevision {
            $locked = Page::query()->whereKey($page->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->archived_at !== null) {
                throw new InvalidArgumentException('Archived pages are read-only until restored.');
            }
            if ($locked->current_draft_revision_id !== $expectedRevisionId) {
                throw new StaleDraftException;
            }
            $type = $this->types->get($locked->type);
            $this->templates->get($templateKey);
            if (! in_array($templateKey, $type['templates'], true) || count($sections) < $type['min'] || count($sections) > $type['max']) {
                throw new InvalidArgumentException('Template or section count is incompatible with the page type.');
            }
            $data = Validator::make(compact('title', 'slug'), [
                'title' => ['required', 'string', 'max:255'],
                'slug' => SlugRules::for($locked->locale, $locked->getKey()),
            ])->validate();
            $normalizedSections = array_map(fn (array $section): array => $this->sections->normalize($section, $locked->type), $sections);
            $keys = array_column($normalizedSections, 'key');
            if (count($keys) !== count(array_unique($keys))) {
                throw new InvalidArgumentException('Section keys must be unique.');
            }
            $payload = $this->payloads->normalize(['page' => ['title' => $data['title']], 'sections' => $normalizedSections]);
            $checksum = $this->payloads->checksum($payload);
            $current = ContentRevision::query()->whereKey($expectedRevisionId)->firstOrFail();
            if ($checksum === $current->checksum && $locked->title === $data['title'] && $locked->slug === $data['slug'] && $locked->template_key === $templateKey) {
                return $current;
            }
            $revision = ContentRevision::query()->create([
                'resource_type' => Page::class,
                'resource_id' => $locked->getKey(),
                'revision_number' => $current->revision_number + 1,
                'schema_version' => 1,
                'payload' => $payload,
                'checksum' => $checksum,
                'sanitizer_version' => '1',
                'change_summary' => trim((string) $summary) ?: null,
                'created_by' => $actor->getKey(),
                'created_at' => now('UTC'),
            ]);
            $this->attachMediaUsages($revision, $normalizedSections);
            $locked->forceFill([
                'title' => $data['title'],
                'slug' => $data['slug'],
                'template_key' => $templateKey,
                'current_draft_revision_id' => $revision->getKey(),
                'updated_by' => $actor->getKey(),
            ])->save();
            $this->audit->handle('content.page.draft-saved', $locked, $actor, ['revision_id' => $current->getKey(), 'revision_number' => $current->revision_number], [
                'revision_id' => $revision->getKey(),
                'revision_number' => $revision->revision_number,
                'section_types' => array_column($normalizedSections, 'type'),
                'section_count' => count($normalizedSections),
                'checksum' => $checksum,
            ], PermissionRegistry::PAGES_EDIT, $summary);

            return $revision;
        }, 3);
    }

    /** @param list<array<string, mixed>> $sections */
    private function attachMediaUsages(ContentRevision $revision, array $sections): void
    {
        foreach ($sections as $position => $section) {
            foreach ($this->mediaFields($section) as $role => $media) {
                $asset = MediaAsset::query()->whereKey($media['asset_id'])->firstOrFail();
                if ($asset->state !== MediaAssetState::Ready) {
                    throw new InvalidArgumentException('Only ready media can be selected.');
                }
                $decorative = (bool) $media['decorative'];
                $alt = trim((string) ($media['alt_override'] ?? ''));
                if (! $decorative && $alt === '' && trim((string) $asset->default_alt_text) === '') {
                    throw new InvalidArgumentException('Informative media requires meaningful effective alt text.');
                }
                MediaUsage::query()->create([
                    'id' => (string) Str::ulid(),
                    'media_asset_id' => $asset->getKey(),
                    'owner_type' => ContentRevision::class,
                    'owner_identifier' => $revision->getKey(),
                    'field_role' => $section['key'].':'.$role,
                    'locale' => 'en',
                    'alt_text_override' => $alt === '' ? null : $alt,
                    'decorative_override' => $decorative,
                    'sort_order' => $position,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, array<string, mixed>>
     */
    private function mediaFields(array $section): array
    {
        $data = $section['data'];
        $result = [];
        foreach (['desktop_media', 'mobile_media', 'media', 'background_media'] as $role) {
            if (is_array($data[$role] ?? null)) {
                $result[$role] = $data[$role];
            }
        }
        foreach ($data['cards'] ?? [] as $index => $card) {
            if (is_array($card['media'] ?? null)) {
                $result['card_'.$index.'_media'] = $card['media'];
            }
        }

        return $result;
    }
}
