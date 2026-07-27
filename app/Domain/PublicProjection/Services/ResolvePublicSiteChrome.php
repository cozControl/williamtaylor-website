<?php

namespace App\Domain\PublicProjection\Services;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\PublicProjection\Data\PublicAnnouncementView;
use App\Domain\PublicProjection\Data\PublicFooterGroupView;
use App\Domain\PublicProjection\Data\PublicLinkView;
use App\Domain\PublicProjection\Data\PublicNavigationItemView;
use App\Domain\PublicProjection\Data\PublicNavigationView;
use App\Domain\PublicProjection\Data\PublicSiteChromeView;
use App\Domain\PublicProjection\Data\PublicSiteProfileView;
use App\Domain\PublicProjection\Data\PublicSocialLinkView;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResolvePublicSiteChrome
{
    private ?PublicSiteChromeView $resolved = null;

    /** @var array<string, MediaAsset> */
    private array $mediaAssets = [];

    public function __construct(
        private SiteContentTypeRegistry $types,
        private MediaProvider $media,
        private PublicSiteContentCache $cache,
    ) {}

    public function resolve(): PublicSiteChromeView
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        if (! config('public_site_content.enabled')) {
            return $this->resolved = PublicSiteChromeView::fallback();
        }

        return $this->resolved = new PublicSiteChromeView(
            navigation: $this->surface(SiteContentTypeRegistry::PRIMARY_NAVIGATION, fn (array $payload) => $this->navigation($payload)),
            footerGroups: $this->surface(SiteContentTypeRegistry::FOOTER_NAVIGATION, fn (array $payload) => $this->footer($payload)),
            announcement: $this->announcement(),
            profile: $this->surface(SiteContentTypeRegistry::SITE_PROFILE, fn (array $payload) => $this->profile($payload)),
        );
    }

    private function surface(string $type, callable $build): mixed
    {
        try {
            $resource = SiteContent::query()
                ->where('type', $type)
                ->where('key', $type)
                ->where('locale', config('public_site_content.locale'))
                ->whereNull('archived_at')
                ->with('publicationState.currentPublicRevision')
                ->first();

            return $resource ? $this->project($resource, $build) : null;
        } catch (Throwable $exception) {
            $this->fallback($type, $exception);

            return null;
        }
    }

    private function announcement(): ?PublicAnnouncementView
    {
        try {
            $resources = SiteContent::query()
                ->where('type', SiteContentTypeRegistry::ANNOUNCEMENT)
                ->where('locale', config('public_site_content.locale'))
                ->whereNull('archived_at')
                ->with('publicationState.currentPublicRevision')
                ->get();
            $effective = [];
            foreach ($resources as $resource) {
                $projection = $this->project($resource, function (array $payload): ?PublicAnnouncementView {
                    $now = CarbonImmutable::now('UTC');
                    if (($payload['effective_from'] ?? null) && CarbonImmutable::parse($payload['effective_from'])->utc()->isAfter($now)) {
                        return null;
                    }
                    if (($payload['effective_until'] ?? null) && ! CarbonImmutable::parse($payload['effective_until'])->utc()->isAfter($now)) {
                        return null;
                    }

                    return new PublicAnnouncementView(
                        message: $payload['message'],
                        cta: $payload['cta'] ? $this->link($payload['cta'], $payload['cta_label'] ?: $payload['message']) : null,
                        variant: $payload['variant'],
                        dismissible: $payload['dismissible'],
                        accessibilityLabel: $payload['accessibility_label'] ?: null,
                    );
                });
                if ($projection !== null) {
                    $effective[] = $projection;
                }
            }
            if (count($effective) > 1) {
                throw new \RuntimeException('Multiple effective announcements were resolved.');
            }

            return $effective[0] ?? null;
        } catch (Throwable $exception) {
            $this->fallback(SiteContentTypeRegistry::ANNOUNCEMENT, $exception);

            return null;
        }
    }

    private function project(SiteContent $resource, callable $build): mixed
    {
        $revision = $resource->publicationState?->currentPublicRevision;
        if (! $revision instanceof ContentRevision
            || $revision->resource_type !== SiteContent::class
            || $revision->resource_id !== $resource->getKey()) {
            return null;
        }
        $definition = $this->types->get($resource->type);
        $payload = $definition->validate($revision->payload);
        $key = implode(':', [
            'public-site-content',
            config('public_site_content.projection_version'),
            $resource->type,
            $resource->getKey(),
            $revision->getKey(),
            $revision->checksum,
            $resource->locale,
            $this->types->checksum(),
            $this->mediaIdentity($resource->type, $payload),
        ]);

        try {
            $cachedPayload = $this->cache->remember((string) $resource->getKey(), $key, fn (): array => $payload);

            return $build($cachedPayload);
        } catch (Throwable $exception) {
            Log::warning('Public Site Content cache failed; attempting direct projection.', [
                'resource_id' => $resource->getKey(),
                'type' => $resource->type,
                'revision_id' => $revision->getKey(),
                'classification' => 'cache_failure',
            ]);

            return $build($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function navigation(array $payload): PublicNavigationView
    {
        return new PublicNavigationView(array_values(array_map(
            fn (array $item): PublicNavigationItemView => $this->navigationItem($item),
            $payload['items'],
        )));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function navigationItem(array $item): PublicNavigationItemView
    {
        return new PublicNavigationItemView(
            key: $item['key'],
            link: $this->link($item['link'], $item['label'], $item['new_tab']),
            visibility: $item['visibility'],
            children: array_values(array_map(fn (array $child): PublicNavigationItemView => $this->navigationItem($child), $item['children'] ?? [])),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<PublicFooterGroupView>
     */
    private function footer(array $payload): array
    {
        return array_values(array_map(fn (array $group): PublicFooterGroupView => new PublicFooterGroupView(
            key: $group['key'],
            label: $group['label'],
            links: array_values(array_map(fn (array $item): PublicLinkView => $this->link($item['link'], $item['label'], $item['new_tab']), $group['links'])),
        ), $payload['groups']));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function profile(array $payload): PublicSiteProfileView
    {
        return new PublicSiteProfileView(
            brandName: $payload['brand']['name'],
            brandDescription: $payload['brand']['description'] ?? '',
            headerLogoUrl: $this->mediaUrl($payload['brand']['header_logo_id'] ?? null),
            footerLogoUrl: $this->mediaUrl($payload['brand']['footer_logo_id'] ?? null),
            email: $payload['contact']['email'] ?? '',
            telephone: $payload['contact']['telephone'] ?? '',
            whatsApp: $payload['contact']['whatsapp'] ?? '',
            address: $payload['contact']['address'] ?? '',
            businessHours: $payload['contact']['business_hours'] ?? '',
            socialLinks: array_values(array_map(fn (array $link): PublicSocialLinkView => new PublicSocialLinkView($link['platform'], $link['url'], $link['label']), $payload['social_links'])),
            footerDescription: $payload['footer']['description'] ?? '',
            copyright: $payload['footer']['copyright'] ?? '',
            newsletterHeading: $payload['footer']['newsletter_heading'] ?? '',
            newsletterCopy: $payload['footer']['newsletter_copy'] ?? '',
        );
    }

    /**
     * @param  array{type: string, value: string}  $link
     */
    private function link(array $link, string $label, bool $newTab = false): PublicLinkView
    {
        return new PublicLinkView($label, $link['value'], $newTab || $link['type'] === 'external_url');
    }

    private function mediaUrl(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }
        $asset = $this->mediaAssets[$id] ?? MediaAsset::query()->whereKey($id)->first();
        if (! $asset || $asset->state !== MediaAssetState::Ready || $asset->archived_at !== null) {
            throw new \RuntimeException('Required projected Media is unavailable.');
        }

        return $this->media->deliveryUrl(
            $asset->provider_public_id,
            $asset->resource_type->value,
            'site_logo',
            $asset->focal_x !== null ? (float) $asset->focal_x : null,
            $asset->focal_y !== null ? (float) $asset->focal_y : null,
        );
    }

    /** @param array<string, mixed> $payload */
    private function mediaIdentity(string $type, array $payload): string
    {
        if ($type !== SiteContentTypeRegistry::SITE_PROFILE) {
            return 'no-media';
        }
        $ids = array_values(array_filter([
            $payload['brand']['header_logo_id'] ?? null,
            $payload['brand']['footer_logo_id'] ?? null,
        ], is_string(...)));
        if ($ids === []) {
            return 'no-media';
        }
        $assets = MediaAsset::query()->whereKey($ids)->get();
        foreach ($assets as $asset) {
            $this->mediaAssets[(string) $asset->getKey()] = $asset;
        }

        return hash('sha256', implode('|', array_map(function (string $id): string {
            $asset = $this->mediaAssets[$id] ?? null;

            return $asset ? implode(':', [$id, $asset->provider_public_id, $asset->state->value, (string) $asset->archived_at, (string) $asset->updated_at]) : $id.':missing';
        }, $ids)));
    }

    private function fallback(string $type, Throwable $exception): void
    {
        Log::warning('Public Site Content static fallback activated.', [
            'type' => $type,
            'classification' => $exception instanceof \InvalidArgumentException ? 'invalid_projection' : 'projection_failure',
            'correlation_id' => (string) str()->ulid(),
        ]);
    }
}
