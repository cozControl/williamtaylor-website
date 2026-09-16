<?php

namespace App\Domain\Payments\Support;

use App\Domain\Checkout\Enums\OrderStatus;
use App\Domain\Checkout\Models\Order;
use App\Domain\Payments\MobileMoneyPayment;
use App\Domain\Payments\Models\Payment;

final class CheckoutPaymentState
{
    /** @return array{order_status: string, payment_status: string, attempt_status: ?string, retry_allowed: bool, poll: bool} */
    public static function for(Order $order, ?Payment $payment): array
    {
        $pending = $order->status === OrderStatus::PendingConfirmation && $order->payment_status === 'unpaid';

        return [
            'order_status' => $order->status->value,
            'payment_status' => $order->payment_status,
            'attempt_status' => $payment?->reconciliation_issue ? 'attention_required' : $payment?->status->value,
            'retry_allowed' => $pending && ($payment?->retrySafe() ?? false),
            'poll' => $pending && $payment !== null && $payment->active_order_id !== null
                && in_array($payment->status->value, ['created', 'initiating', 'pending', 'processing', 'attention_required'], true)
                && ! in_array($payment->reconciliation_issue, MobileMoneyPayment::REVIEW_REASONS, true),
        ];
    }
}
