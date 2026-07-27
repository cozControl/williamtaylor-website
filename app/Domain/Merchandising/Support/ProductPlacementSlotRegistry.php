<?php

namespace App\Domain\Merchandising\Support;

use InvalidArgumentException;

final class ProductPlacementSlotRegistry
{
    /** @return array{key: string, label: string, target_type: string, minimum: int, maximum: int, ordered: bool, duplicate_targets: bool, requires_eligibility: bool, scheduling: bool, audience_targeting: bool, locale_targeting: bool, campaign_required: bool} */
    public function get(string $key): array
    {
        if ($key !== 'homepage-featured-products') {
            throw new InvalidArgumentException('Unsupported Product placement slot.');
        }

        return [
            'key' => 'homepage-featured-products', 'label' => 'Homepage featured products',
            'target_type' => 'product', 'minimum' => 0, 'maximum' => 5, 'ordered' => true,
            'duplicate_targets' => false, 'requires_eligibility' => true, 'scheduling' => false,
            'audience_targeting' => false, 'locale_targeting' => false, 'campaign_required' => false,
        ];
    }
}
