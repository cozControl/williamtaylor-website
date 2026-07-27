<?php

namespace App\Domain\Merchandising\Support;

final class ActiveOrderingKeys
{
    public static function active(string ...$parts): string
    {
        return hash('sha256', implode('|', $parts));
    }

    public static function archived(string $id): string
    {
        return 'archived:'.$id;
    }
}
