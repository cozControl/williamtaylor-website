<?php

namespace App\Domain\PublicProjection\Services;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\SectionRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\PublicProjection\Data\Page\PublicCtaSectionView;
use App\Domain\PublicProjection\Data\Page\PublicEditorialSplitSectionView;
use App\Domain\PublicProjection\Data\Page\PublicHeroSectionView;
use App\Domain\PublicProjection\Data\Page\PublicLinkView;
use App\Domain\PublicProjection\Data\Page\PublicMediaView;
use App\Domain\PublicProjection\Data\Page\PublicPageView;
use App\Domain\PublicProjection\Data\Page\PublicPromotionalCardsSectionView;
use App\Domain\PublicProjection\Data\Page\PublicPromotionalCardView;
use App\Domain\PublicProjection\Data\Page\PublicRichTextSectionView;
use App\Domain\PublicProjection\Registry\PublicPageTemplateRegistry;
use InvalidArgumentException;

final class BuildPublicPageProjection
{
    public function __construct(private SectionRegistry $sections, private PublicPageTemplateRegistry $templates, private MediaProvider $provider) {}

    public function build(Page $page, ContentRevision $revision): PublicPageView
    {
        if ($revision->resource_type !== Page::class || $revision->resource_id !== $page->getKey()) {
            throw new InvalidArgumentException('Public revision ownership is invalid.');
        }
        $template = $this->templates->get($page->template_key);
        if ($page->type !== $template['type'] || $revision->schema_version !== 1) {
            throw new InvalidArgumentException('Page type, template or schema is unsupported.');
        }
        $normalized = [];
        $counts = [];
        foreach (($revision->payload['sections'] ?? []) as $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException('Section payload is invalid.');
            } $section = $this->sections->normalize($raw, $page->type);
            if (! in_array($section['type'], $template['sections'], true)) {
                throw new InvalidArgumentException('Section is unsupported.');
            } $counts[$section['type']] = ($counts[$section['type']] ?? 0) + 1;
            $normalized[] = $section;
        }
        foreach ($template['required'] as $required) {
            if (($counts[$required] ?? 0) !== $template['cardinality'][$required]) {
                throw new InvalidArgumentException('Required section cardinality is invalid.');
            }
        }

        return new PublicPageView('about', $page->title, $page->template_key, array_map(fn (array $s) => $this->section($s), $normalized));
    }

    /** @param array<string, mixed> $section */
    private function section(array $section): object
    {
        $d = $section['data'];

        return match ($section['type']) {
            'hero' => new PublicHeroSectionView($d['eyebrow'], $d['heading'], $d['copy'], $this->media($d['desktop_media'], 'hero_desktop'), $this->link($d['primary_cta'])),
            'editorial_split' => new PublicEditorialSplitSectionView('', $d['heading'], $d['copy'], $this->media($d['media'], 'editorial_content'), $this->link($d['cta'])),
            'promotional_cards' => new PublicPromotionalCardsSectionView($d['heading'], array_values(array_map(fn (array $c) => new PublicPromotionalCardView($c['heading'], $c['copy']), $d['cards']))),
            'rich_text' => new PublicRichTextSectionView($d['html']),
            'cta' => new PublicCtaSectionView($d['heading'], $d['copy'], $this->link($d['primary_cta']) ?? throw new InvalidArgumentException('CTA is required.')),
            default => throw new InvalidArgumentException('Section is unsupported.'),
        };
    }

    /** @param array<string, mixed>|null $link */
    private function link(?array $link): ?PublicLinkView
    {
        return $link ? new PublicLinkView($link['label'], $link['target']) : null;
    }

    /** @param array<string, mixed>|null $value */
    private function media(?array $value, string $profile): ?PublicMediaView
    {
        if ($value === null) {
            return null;
        }
        $asset = MediaAsset::query()->whereKey($value['asset_id'])->with(['versions' => fn ($q) => $q->where('is_current', true)])->first();
        if (! $asset || $asset->state !== MediaAssetState::Ready || $asset->archived_at !== null || $asset->versions->count() !== 1) {
            throw new InvalidArgumentException('Required Media is unavailable.');
        }
        $decorative = (bool) $value['decorative'];
        $alt = $decorative ? '' : trim((string) ($value['alt_override'] ?: $asset->default_alt_text));
        if (! $decorative && $alt === '') {
            throw new InvalidArgumentException('Meaningful Media requires alternative text.');
        }

        return new PublicMediaView($this->provider->deliveryUrl($asset->provider_public_id, $asset->resource_type->value, $profile, $asset->focal_x !== null ? (float) $asset->focal_x : null, $asset->focal_y !== null ? (float) $asset->focal_y : null), $alt, $decorative);
    }
}
