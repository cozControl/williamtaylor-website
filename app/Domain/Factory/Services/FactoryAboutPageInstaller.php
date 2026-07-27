<?php

namespace App\Domain\Factory\Services;

use App\Domain\Content\Actions\CreatePageDraft;
use App\Domain\Content\Actions\SavePageDraftRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\RevisionPayload;
use App\Domain\Content\Support\SectionRegistry;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Publishing\Actions\ApprovePageRevision;
use App\Domain\Publishing\Actions\PublishApprovedPageRevision;
use App\Domain\Publishing\Actions\SubmitPageForReview;
use App\Domain\Publishing\Support\PublicationFingerprint;
use App\Models\User;
use RuntimeException;

final class FactoryAboutPageInstaller
{
    public function __construct(private FactoryManifest $manifest, private RevisionPayload $payloads, private CreatePageDraft $create, private SavePageDraftRevision $save, private SubmitPageForReview $submit, private ApprovePageRevision $approve, private PublishApprovedPageRevision $publish, private PublicationFingerprint $fingerprints) {}

    /** @return array{created:int,reused:int,restored:int} */
    public function apply(User $actor, bool $restore = false): array
    {
        $definition = $this->manifest->about();
        if ($definition === null) {
            return ['created' => 0, 'reused' => 0, 'restored' => 0];
        }
        $page = Page::query()->where('slug', 'about')->where('locale', 'en')->first();
        $created = $page === null;
        if ($created) {
            $page = $this->create->handle($actor, $definition['type'], $definition['title'], $definition['slug'], $definition['locale'], $definition['template']);
        }
        $sections = $this->resolveMedia($definition['sections']);
        $normalized = array_map(fn (array $section) => app(SectionRegistry::class)->normalize($section, $definition['type']), $sections);
        $expected = $this->payloads->checksum(['page' => ['title' => $definition['title']], 'sections' => $normalized]);
        if ($page->publicationState?->currentPublicRevision?->checksum === $expected) {
            return ['created' => 0, 'reused' => 1, 'restored' => 0];
        }
        if (! $created && ! $restore) {
            throw new RuntimeException('Factory About Page has drift. Use the scoped reset command to restore it.');
        }
        $this->save->handle($actor, $page, $page->current_draft_revision_id, $definition['title'], $definition['slug'], $definition['template'], $sections, FactoryManifest::VERSION_2.' baseline');
        $page = $page->fresh();
        $this->submit->handle($actor, $page, 'Factory v2 baseline review.', $this->fingerprints->for($page), true, 'Factory v2 baseline restoration.');
        $page = $page->fresh();
        $this->approve->handle($actor, $page, 'Factory v2 baseline approved.', $this->fingerprints->for($page));
        $page = $page->fresh();
        $this->publish->handle($actor, $page, $this->fingerprints->for($page));

        return ['created' => $created ? 1 : 0, 'reused' => 0, 'restored' => $created ? 0 : 1];
    }

    /** @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    private function resolveMedia(array $sections): array
    {
        foreach ($sections as &$section) {
            $data = &$section['data'];
            foreach (['desktop_media', 'mobile_media', 'media', 'background_media'] as $field) {
                $value = $data[$field] ?? null;
                if (! is_array($value) || ! isset($value['logical_key'])) {
                    continue;
                }
                $asset = MediaAsset::query()->where('provider_metadata->logical_key', $value['logical_key'])->first();
                $data[$field] = $asset ? ['asset_id' => (string) $asset->getKey(), 'alt_override' => $value['alt_override'] ?? null, 'decorative' => (bool) ($value['decorative'] ?? false)] : null;
            }
        }
        unset($section, $data);

        return $sections;
    }
}
