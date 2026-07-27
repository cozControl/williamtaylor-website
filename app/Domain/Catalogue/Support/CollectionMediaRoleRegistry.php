<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use InvalidArgumentException;

final class CollectionMediaRoleRegistry
{
    public const CARD = 'card';

    public const HERO = 'hero';

    /** @return array{singular: bool, ordered: bool, meaningful: bool, effective_alt_required: bool, resource_type: MediaResourceType, allowed_states: list<MediaAssetState>, maximum: int, required_for_readiness: bool} */
    public function get(string $ownerType, string $role): array
    {
        if ($ownerType !== Collection::class || ! in_array($role, [self::CARD, self::HERO], true)) {
            throw new InvalidArgumentException('Unsupported Collection media owner or role.');
        }

        return ['singular' => true, 'ordered' => false, 'meaningful' => true, 'effective_alt_required' => true, 'resource_type' => MediaResourceType::Image, 'allowed_states' => [MediaAssetState::Ready], 'maximum' => 1, 'required_for_readiness' => true];
    }

    /** @return list<string> */
    public function roles(): array
    {
        return [self::CARD, self::HERO];
    }
}
