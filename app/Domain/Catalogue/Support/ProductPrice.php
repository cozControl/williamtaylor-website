<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use InvalidArgumentException;

final class ProductPrice
{
    public function parse(?string $major, string $currency = 'TZS'): ?int
    {
        $value = preg_replace('/[^0-9]/', '', (string) $major);
        if ($value === '') {
            return null;
        }
        $amount = (int) $value;
        if ($amount < 0 || $amount > 9999999999) {
            throw new InvalidArgumentException('Price is outside the supported range.');
        }

        return $amount * $this->factor($currency);
    }

    public function effectiveMinor(Product $product, ?ProductVariant $variant = null): ?int
    {
        if ($variant !== null && $variant->price_override_minor !== null) {
            return $variant->price_override_minor;
        }

        return $product->base_price_minor;
    }

    public function format(?int $minor, string $currency): ?string
    {
        return $minor === null ? null : strtoupper($currency).' '.number_format($minor / $this->factor($currency), 0);
    }

    public function majorInput(?int $minor, string $currency): string
    {
        return $minor === null ? '' : (string) intdiv($minor, $this->factor($currency));
    }

    private function factor(string $currency): int
    {
        if (strtoupper($currency) !== 'TZS') {
            throw new InvalidArgumentException('Only TZS is supported by the current storefront.');
        }

        return 100;
    }
}
