<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Support\CommerceLifecycleMutation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property PaymentStatus $status
 * @property CarbonImmutable|null $io_lease_until
 * @property CarbonImmutable|null $next_reconcile_at
 * @property CarbonImmutable|null $request_started_at
 * @property CarbonImmutable|null $last_verified_at
 * @property array<string, mixed> $request_snapshot
 * @property CarbonImmutable|null $expires_at
 */
final class Payment extends Model
{
    use HasUlids;

    protected $table = 'commerce_payments';

    protected $guarded = [];

    protected $hidden = ['return_reference', 'provider_checkout_url', 'attempt_key', 'customer_phone', 'request_snapshot', 'last_evidence', 'io_lease_token'];

    public function maskedPhone(): string
    {
        return $this->customer_phone ? '+255 ••• ••• '.substr($this->customer_phone, -3) : 'Not recorded';
    }

    public function retrySafe(): bool
    {
        return $this->status === PaymentStatus::Failed && $this->active_order_id === null && $this->provider_payment_reference === null && $this->provider_session_reference === null && $this->reconciliation_issue === null;
    }

    protected function casts(): array
    {
        return ['last_evidence' => 'array', 'customer_phone' => 'encrypted', 'request_snapshot' => 'encrypted:array', 'last_verified_at' => 'immutable_datetime', 'expired_at' => 'immutable_datetime', 'status' => PaymentStatus::class, 'expected_amount_internal_minor' => 'integer', 'provider_amount_tzs' => 'integer', 'request_started_at' => 'immutable_datetime', 'io_lease_until' => 'immutable_datetime', 'next_reconcile_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'failed_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        self::updating(function (self $payment) {
            if ($payment->isDirty('status') && in_array($payment->status, [PaymentStatus::Completed, PaymentStatus::Expired, PaymentStatus::Cancelled, PaymentStatus::Voided], true) && ! app(CommerceLifecycleMutation::class)->active()) {
                throw new \LogicException('Final payment transitions require verified provider evidence.');
            }
            if ($payment->isDirty(['order_id', 'provider', 'method', 'customer_phone', 'request_snapshot', 'currency', 'expected_amount_internal_minor', 'provider_amount_tzs', 'attempt_key', 'return_reference']) || ($payment->getOriginal('provider_session_reference') !== null && $payment->isDirty('provider_session_reference')) || ($payment->getOriginal('provider_payment_reference') !== null && $payment->isDirty('provider_payment_reference'))) {
                throw new \LogicException('Payment commercial identity cannot change.');
            }
        });
        self::deleting(fn () => throw new \LogicException('Payment history cannot be deleted.'));
    }
}
