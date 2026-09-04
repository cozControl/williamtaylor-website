<?php

namespace App\Domain\Orders\Support;

use Illuminate\Support\Str;

final class OrderNumber
{
    public static function generate(): string
    {
        return 'WT-'.now('UTC')->format('Y').'-'.strtoupper(Str::random(10));
    }

    public static function receipt(): string
    {
        return 'DEMO-RCT-'.now('UTC')->format('Y').'-'.strtoupper(Str::random(12));
    }
}
