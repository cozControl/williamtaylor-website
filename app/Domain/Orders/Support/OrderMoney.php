<?php

namespace App\Domain\Orders\Support;

use InvalidArgumentException;

final class OrderMoney
{
    public static function lineTotal(int $quantity, int $unitMinor): int
    {
        if ($quantity < 1 || $unitMinor < 0) {
            throw new InvalidArgumentException('Quantity must be positive and unit amount cannot be negative.');
        }

        $total = $quantity * $unitMinor;
        if ($unitMinor !== 0 && intdiv($total, $unitMinor) !== $quantity) {
            throw new InvalidArgumentException('Line total exceeds the supported integer boundary.');
        }

        return $total;
    }

    public static function format(int $minor, string $currency): string
    {
        return sprintf('%s %s', strtoupper($currency), number_format($minor / 100, 2));
    }
}
