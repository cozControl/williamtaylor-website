<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use InvalidArgumentException;

final class ProductMediaAccessibility
{
    public function effectiveAlt(MediaAsset $asset, ?string $override): string
    {
        return trim($override ?? (string) $asset->default_alt_text);
    }

    public function assertAssignable(MediaAsset $asset, ?string $override, ?bool $decorative): void
    {
        if ($asset->state !== MediaAssetState::Ready || $asset->resource_type !== MediaResourceType::Image || $asset->confirmed_at === null) {
            throw new InvalidArgumentException('Product media must be a ready image.');
        }
        if ($decorative === true) {
            throw new InvalidArgumentException('Product media cannot be decorative.');
        }

        $alt = $this->effectiveAlt($asset, $override);
        if ($alt === '' || $alt !== strip_tags($alt)) {
            throw new InvalidArgumentException('Product media requires plain-text alternative text.');
        }
    }

    public function isUsable(MediaUsage $usage): bool
    {
        if (! $usage->asset) {
            return false;
        }

        try {
            $this->assertAssignable($usage->asset, $usage->alt_text_override, $usage->decorative_override);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
