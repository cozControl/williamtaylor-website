<?php

namespace App\Domain\Media\Support;

use InvalidArgumentException;

final class TransformationProfiles
{
    /** @return array<string, array<string, int|string|bool>> */
    public function all(): array
    {
        return [
            'admin_thumbnail' => ['width' => 320, 'height' => 320, 'crop' => 'fill', 'quality' => 'auto', 'format' => 'auto'],
            'admin_preview' => ['width' => 1200, 'height' => 1200, 'crop' => 'limit', 'quality' => 'auto', 'format' => 'auto'],
            'product_card' => ['width' => 640, 'height' => 800, 'crop' => 'fill', 'quality' => 'auto', 'format' => 'auto'],
            'collection_card' => ['width' => 900, 'height' => 1200, 'crop' => 'fill', 'quality' => 'auto', 'format' => 'auto'],
            'product_gallery' => ['width' => 1400, 'height' => 1750, 'crop' => 'fill', 'quality' => 'auto', 'format' => 'auto'],
            'hero_desktop' => ['width' => 1920, 'height' => 900, 'crop' => 'fill', 'quality' => 'auto', 'format' => 'auto'],
            'hero_mobile' => ['width' => 750, 'height' => 1000, 'crop' => 'fill', 'quality' => 'auto', 'format' => 'auto'],
            'editorial_content' => ['width' => 1400, 'height' => 1400, 'crop' => 'limit', 'quality' => 'auto', 'format' => 'auto'],
            'site_logo' => ['width' => 680, 'height' => 160, 'crop' => 'fit', 'quality' => 'auto', 'format' => 'auto'],
            'social_open_graph' => ['width' => 1200, 'height' => 630, 'crop' => 'fill', 'quality' => 'auto', 'format' => 'auto'],
            'video_poster' => ['width' => 1280, 'height' => 720, 'crop' => 'fill', 'quality' => 'auto', 'format' => 'jpg'],
        ];
    }

    /** @return array<string, int|string|bool> */
    public function get(string $name): array
    {
        return $this->all()[$name] ?? throw new InvalidArgumentException('Unknown media transformation profile.');
    }
}
