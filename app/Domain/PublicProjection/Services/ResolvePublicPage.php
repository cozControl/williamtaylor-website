<?php

namespace App\Domain\PublicProjection\Services;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Support\SectionRegistry;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Publication\Enums\RolloutMode;
use App\Domain\Publication\Support\RolloutModeResolver;
use App\Domain\PublicProjection\Data\Page\PublicPageView;
use App\Domain\PublicProjection\Registry\PublicPageRouteRegistry;
use App\Domain\PublicProjection\Registry\PublicPageTemplateRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResolvePublicPage
{
    public function __construct(private PublicPageRouteRegistry $routes, private PublicPageTemplateRegistry $templates, private SectionRegistry $sections, private BuildPublicPageProjection $builder, private PublicPageProjectionCache $cache, private RolloutModeResolver $rollout) {}

    public function resolve(string $key): ?PublicPageView
    {
        if (! config('public_page_projection.enabled')) {
            return null;
        }
        $mode = $this->rollout->resolve('page');
        if (in_array($mode, [RolloutMode::Static, RolloutMode::EmergencyDisabled], true)) {
            return null;
        }
        try {
            $route = $this->routes->get($key);
            $page = Page::query()->where('slug', $route['page_key'])->where('locale', config('public_page_projection.locale'))->whereNull('archived_at')->with('publicationState.currentPublicRevision')->first();
            if (! $page || $page->type !== $route['type'] || $page->template_key !== $route['template']) {
                return null;
            }
            $revision = $page->publicationState?->currentPublicRevision;
            if (! $revision instanceof ContentRevision || $revision->resource_type !== Page::class || $revision->resource_id !== $page->getKey()) {
                return null;
            }
            $keyValue = implode(':', ['public-page', config('public_page_projection.schema_version'), $page->getKey(), $revision->getKey(), $revision->checksum, $this->routes->checksum(), $this->templates->checksum(), hash('sha256', json_encode($this->sections->all(), JSON_THROW_ON_ERROR)), config('public_page_projection.locale'), $this->mediaIdentity($revision)]);
            try {
                $projection = $this->cache->remember((string) $page->getKey(), $keyValue, fn () => $this->builder->build($page, $revision));

                return $mode === RolloutMode::Enabled ? $projection : null;
            } catch (Throwable $cacheFailure) {
                $projection = $this->builder->build($page, $revision);

                return $mode === RolloutMode::Enabled ? $projection : null;
            }
        } catch (Throwable $e) {
            Log::warning('Public Page static fallback activated.', ['route_key' => $key, 'classification' => 'projection_failure', 'correlation_id' => (string) str()->ulid()]);

            return null;
        }
    }

    private function mediaIdentity(ContentRevision $revision): string
    {
        $ids = [];
        foreach (($revision->payload['sections'] ?? []) as $section) {
            $data = is_array($section) && is_array($section['data'] ?? null) ? $section['data'] : [];
            foreach (['desktop_media', 'mobile_media', 'media', 'background_media'] as $field) {
                if (is_array($data[$field] ?? null) && is_string($data[$field]['asset_id'] ?? null)) {
                    $ids[] = $data[$field]['asset_id'];
                }
            }
            foreach (is_array($data['cards'] ?? null) ? $data['cards'] : [] as $card) {
                if (is_array($card) && is_array($card['media'] ?? null) && is_string($card['media']['asset_id'] ?? null)) {
                    $ids[] = $card['media']['asset_id'];
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return 'no-media';
        }
        $assets = MediaAsset::query()->whereKey($ids)->with(['versions' => fn ($query) => $query->where('is_current', true)])->get()->keyBy('id');
        $identity = array_map(function (string $id) use ($assets): string {
            $asset = $assets->get($id);
            $version = $asset?->versions->sole();

            return $asset && $version ? implode(':', [$id, $version->getKey(), $version->provider_asset_id, (string) $version->provider_version, $asset->state->value, (string) $asset->archived_at]) : $id.':missing';
        }, $ids);

        return hash('sha256', implode('|', $identity));
    }
}
