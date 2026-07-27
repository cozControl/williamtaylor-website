<?php

namespace App\Domain\Catalogue\Support;

use InvalidArgumentException;

final class CollectionTypeRegistry
{
    public const CURATED = 'curated';

    /** @return array{key: string, label: string, membership_mode: string, manual_ordering: bool, product_membership: bool, media_required: bool, can_be_ready: bool, maximum_nesting_depth: int, collection_membership: bool, dynamic_query_rules: bool, deprecated: bool} */
    public function get(string $key): array
    {
        if ($key !== self::CURATED) {
            throw new InvalidArgumentException('Unsupported Collection type.');
        }

        return ['key' => self::CURATED, 'label' => 'Curated collection', 'membership_mode' => 'manual', 'manual_ordering' => true, 'product_membership' => true, 'media_required' => true, 'can_be_ready' => true, 'maximum_nesting_depth' => 0, 'collection_membership' => false, 'dynamic_query_rules' => false, 'deprecated' => false];
    }
}
