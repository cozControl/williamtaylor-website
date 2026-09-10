<?php

namespace App\Domain\Inventory\Enums;

enum MovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case AdjustmentIn = 'adjustment_in';
    case AdjustmentOut = 'adjustment_out';
    case OrderIssue = 'order_issue';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening Stock', self::Receipt => 'Receipt',
            self::AdjustmentIn => 'Adjustment In', self::AdjustmentOut => 'Adjustment Out',
            self::OrderIssue => 'Order Issue',
        };
    }
}
