<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaUsage;

final class HomepageHotSalePresenter
{
    public function __construct(private MediaProvider $media, private HomepageHotSaleDestinationRegistry $destinations) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $fallback = [...HomepageHero::hotSaleDefaults(), 'managed' => false, 'tiles' => []];
        if (! app(HomepageRenderSnapshot::class)->hasColumns(['hot_sale_managed', 'hot_sale_heading'])) {
            return $fallback;
        }

        $homepage = app(HomepageRenderSnapshot::class)->hero();
        if ($homepage === null || ! $homepage->hot_sale_managed) {
            return $fallback;
        }

        $usages = MediaUsage::query()->with('asset')->where('owner_type', HomepageHero::class)
            ->where('owner_identifier', $homepage->id)->whereIn('field_role', HomepageHero::HOT_SALE_MEDIA_ROLES)
            ->get()->keyBy('field_role');
        $tiles = collect(HomepageHero::HOT_SALE_MEDIA_ROLES)->map(function (string $role, int $position) use ($homepage, $usages): ?array {
            $usage = $usages->get($role);
            $asset = $usage?->asset;
            $effectiveAlt = trim((string) ($usage?->alt_text_override ?: $asset?->default_alt_text));
            if ($asset === null || $asset->state !== MediaAssetState::Ready || ! in_array($asset->resource_type, [MediaResourceType::Image, MediaResourceType::Video], true) || $asset->confirmed_at === null || $asset->archived_at !== null || $effectiveAlt === '') {
                return null;
            }

            return [
                'position' => $position,
                'title' => $homepage->{"hot_sale_tile_{$position}_title"},
                'copy' => $homepage->{"hot_sale_tile_{$position}_copy"},
                'cta_label' => $homepage->{"hot_sale_tile_{$position}_cta_label"},
                'url' => $this->destinations->url($homepage->{"hot_sale_tile_{$position}_destination"}),
                'media_type' => $asset->resource_type->value,
                'media' => $this->media->deliveryUrl($asset->provider_public_id, $asset->resource_type->value, 'product_card', $asset->focal_x === null ? null : (float) $asset->focal_x, $asset->focal_y === null ? null : (float) $asset->focal_y),
                'alt' => $effectiveAlt,
            ];
        })->filter()->values();

        if ($tiles->count() !== 3) {
            return $fallback;
        }

        return [
            ...HomepageHero::hotSaleDefaults(),
            ...$homepage->only(array_keys(HomepageHero::hotSaleDefaults())),
            'managed' => true,
            'tiles' => $tiles->all(),
        ];
    }
}
