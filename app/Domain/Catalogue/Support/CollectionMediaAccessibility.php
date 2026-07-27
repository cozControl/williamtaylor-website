<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use InvalidArgumentException;

final class CollectionMediaAccessibility
{
    public function assertAssignable(MediaAsset $asset, ?string $override, ?bool $decorative): void
    {
        if ($asset->state !== MediaAssetState::Ready || $asset->resource_type !== MediaResourceType::Image || $asset->confirmed_at === null) {
            throw new InvalidArgumentException('Collection media must be a confirmed ready image.');
        }
        $alt = trim($override ?? (string) $asset->default_alt_text);
        if ($decorative === true || $alt === '' || $alt !== strip_tags($alt)) {
            throw new InvalidArgumentException('Collection media requires meaningful plain-text alternative text.');
        }
    }

    public function isUsable(MediaUsage $usage): bool
    {
        try {
            if (! $usage->asset) {
                return false;
            }
            $this->assertAssignable($usage->asset, $usage->alt_text_override, $usage->decorative_override);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
