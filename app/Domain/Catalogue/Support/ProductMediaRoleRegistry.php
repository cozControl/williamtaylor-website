<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use InvalidArgumentException;

final class ProductMediaRoleRegistry
{
    public const PRIMARY = 'primary';

    public const GALLERY = 'gallery';

    /** @return array{singular: bool, ordered: bool, meaningful: bool, effective_alt_required: bool, resource_type: MediaResourceType, allowed_states: list<MediaAssetState>, maximum: int, required_for_readiness: bool} */
    public function get(string $ownerType, string $role): array
    {
        if ($ownerType !== Product::class) {
            throw new InvalidArgumentException('Unsupported product media owner type.');
        }

        return match ($role) {
            self::PRIMARY => ['singular' => true, 'ordered' => false, 'meaningful' => true, 'effective_alt_required' => true, 'resource_type' => MediaResourceType::Image, 'allowed_states' => [MediaAssetState::Ready], 'maximum' => 1, 'required_for_readiness' => true],
            self::GALLERY => ['singular' => false, 'ordered' => true, 'meaningful' => true, 'effective_alt_required' => true, 'resource_type' => MediaResourceType::Image, 'allowed_states' => [MediaAssetState::Ready], 'maximum' => 20, 'required_for_readiness' => false],
            default => throw new InvalidArgumentException('Unsupported product media role.'),
        };
    }

    /** @return list<string> */
    public function roles(string $ownerType): array
    {
        $this->get($ownerType, self::PRIMARY);

        return [self::PRIMARY, self::GALLERY];
    }
}
