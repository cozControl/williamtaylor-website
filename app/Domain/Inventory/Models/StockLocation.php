<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class StockLocation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'fulfillment_enabled' => 'boolean'];
    }

    public static function main(): self
    {
        return self::query()->where('code', 'MAIN')->sole();
    }
}
