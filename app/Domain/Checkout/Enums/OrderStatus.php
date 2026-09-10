<?php

namespace App\Domain\Checkout\Enums;

enum OrderStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Confirmed = 'confirmed';
    case PaymentExpired = 'payment_expired';
    case Cancelled = 'cancelled';
}
