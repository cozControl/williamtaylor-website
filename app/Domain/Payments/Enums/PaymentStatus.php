<?php

namespace App\Domain\Payments\Enums;

enum PaymentStatus: string
{
    case Created = 'created';
    case Initiating = 'initiating';
    case AttentionRequired = 'attention_required';
    case Voided = 'voided';
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
