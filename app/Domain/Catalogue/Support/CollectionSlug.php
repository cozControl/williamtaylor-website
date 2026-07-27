<?php

namespace App\Domain\Catalogue\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;

final class CollectionSlug
{
    /** @var list<string> */
    private const RESERVED = ['admin', 'api', 'collections', 'limited-edition', 'pre-order', 'products', 'shop'];

    public static function normalize(string $value): string
    {
        $slug = Str::slug($value);
        if (strlen($slug) < 3 || in_array($slug, self::RESERVED, true)) {
            throw new InvalidArgumentException('Collection slug is empty, invalid, or reserved.');
        }

        return $slug;
    }
}
