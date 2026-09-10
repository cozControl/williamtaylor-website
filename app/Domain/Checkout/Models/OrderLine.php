<?php

namespace App\Domain\Checkout\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** @property list<string> $options_snapshot */
final class OrderLine extends Model
{
    use HasUlids;

    protected $table = 'commerce_order_lines';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['options_snapshot' => 'array', 'unit_price_minor' => 'integer', 'quantity' => 'integer', 'line_total_minor' => 'integer'];
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Order line snapshots are immutable.'));
        self::deleting(fn () => throw new \LogicException('Order line snapshots cannot be deleted.'));
    }
}
