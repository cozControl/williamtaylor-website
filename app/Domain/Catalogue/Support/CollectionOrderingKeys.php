<?php

namespace App\Domain\Catalogue\Support;

final class CollectionOrderingKeys
{
    public static function active(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    public static function archived(string $id): string
    {
        return hash('sha256', 'archived|'.$id);
    }
}
