<?php

namespace App\Domain\Checkout\Admin;

use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class OrderStatusPresenter
{
    /** @return array<string, string> */
    public function orders(): array
    {
        return ['pending_confirmation' => 'Pending confirmation', 'confirmed' => 'Confirmed', 'payment_expired' => 'Payment expired', 'cancelled' => 'Cancelled'];
    }

    public function label(BackedEnum|string|null $value): string
    {
        $key = $value instanceof BackedEnum ? (string) $value->value : $value;

        return ($this->orders() + ['created' => 'Created', 'initiating' => 'Initiating', 'attention_required' => 'Needs attention', 'voided' => 'Voided', 'unpaid' => 'Unpaid', 'paid' => 'Paid', 'pending' => 'Pending', 'processing' => 'Processing', 'active' => 'Active', 'completed' => 'Completed', 'failed' => 'Failed', 'expired' => 'Expired', 'unfulfilled' => 'Unfulfilled', 'consumed' => 'Consumed', 'released' => 'Released'])[$key ?? ''] ?? 'Not recorded';
    }

    public function tone(BackedEnum|string|null $value): string
    {
        $key = $value instanceof BackedEnum ? $value->value : $value;

        return match ($key) {
            'paid', 'confirmed', 'completed', 'consumed' => 'success',
            'failed', 'attention', 'attention_required' => 'attention',
            default => 'neutral',
        };
    }

    public function date(DateTimeInterface|string|null $value): string
    {
        return $value === null ? 'Not recorded' : CarbonImmutable::parse($value, 'UTC')->timezone(config('app.timezone'))->format('d M Y · H:i');
    }

    public function issue(?string $code): string
    {
        return match ($code) {
            'cancellation_unconfirmed' => 'Cancellation could not be confirmed with Snippe. Check payment status before retrying; stock remains reserved.',
            'evidence_mismatch', 'session_mismatch' => 'Payment information from Snippe does not match this Order. Review the payment in Snippe before taking further action.',
            'reservation_mismatch', 'incompatible_lifecycle' => 'The payment cannot be applied to the current Order or stock commitment. Review the payment and inventory history.',
            'session_outcome_unknown' => 'Session creation has an unresolved outcome. Review Snippe before retrying; stock remains reserved.',
            'http_401', 'http_403', 'configuration' => 'Snippe access needs configuration review. The saved Order remains available.',
            'payment_attempt_failed' => 'The payment attempt failed. The hosted Session may still allow the customer to retry.',
            default => 'Payment verification needs review. Check Snippe and the recorded references before taking further action.',
        };
    }
}
