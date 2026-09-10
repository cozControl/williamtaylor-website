<?php

declare(strict_types=1);

namespace App\Domain\Payments\Snippe;

final class SnippeMoney
{
    public static function tzs(int $minor, string $currency, bool $session = true): int
    {
        if ($currency !== 'TZS' || $minor <= 0 || $minor % 100 !== 0 || ($session && $minor < 50000)) {
            throw new \InvalidArgumentException('The amount cannot be paid through hosted checkout.');
        }

        return intdiv($minor, 100);
    }
}
