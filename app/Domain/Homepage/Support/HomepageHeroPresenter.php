<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaUsage;

final class HomepageHeroPresenter
{
    public const FALLBACK_IMAGE = '/website/images/306170464_Screenshot2026-07-10at215946.png';

    public function __construct(private MediaProvider $media, private HomepageHeroDestinationRegistry $destinations) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        $hero = app(HomepageRenderSnapshot::class)->hero();
        $content = $hero?->only(array_keys(HomepageHero::defaults())) ?? HomepageHero::defaults();
        $usages = $hero === null ? collect() : MediaUsage::query()->with('asset')
            ->where('owner_type', HomepageHero::class)
            ->where('owner_identifier', $hero->id)
            ->whereIn('field_role', [HomepageHero::MEDIA_ROLE, HomepageHero::MOBILE_MEDIA_ROLE])
            ->get()->keyBy('field_role');
        $usage = $usages->get(HomepageHero::MEDIA_ROLE);
        $mobile = $usages->get(HomepageHero::MOBILE_MEDIA_ROLE)?->asset;
        $mobileEligible = $mobile !== null && $mobile->state === MediaAssetState::Ready
            && $mobile->resource_type === MediaResourceType::Image
            && $mobile->confirmed_at !== null && $mobile->archived_at === null;

        return [
            ...$content,
            'primary_cta_url' => $this->destinations->url((string) $content['primary_cta_destination']),
            'secondary_cta_url' => $this->destinations->url((string) $content['secondary_cta_destination']),
            'mobile_background_url' => $mobileEligible ? $this->media->deliveryUrl($mobile->provider_public_id, $mobile->resource_type->value, 'hero_mobile', $mobile->focal_x === null ? null : (float) $mobile->focal_x, $mobile->focal_y === null ? null : (float) $mobile->focal_y) : null,
            'background_url' => $usage === null ? self::FALLBACK_IMAGE : $this->media->deliveryUrl(
                $usage->asset->provider_public_id,
                $usage->asset->resource_type->value,
                'hero_desktop',
                $usage->asset->focal_x === null ? null : (float) $usage->asset->focal_x,
                $usage->asset->focal_y === null ? null : (float) $usage->asset->focal_y,
            ),
        ];
    }
}
