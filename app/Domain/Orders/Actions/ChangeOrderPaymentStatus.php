<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Support\OrderNumber;
use App\Models\User;
use App\Support\Demo\DemoMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ChangeOrderPaymentStatus
{
    public function __construct(private DemoMode $demo, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Order $order, PaymentStatus $next, int $lockVersion, string $reason): Order
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::ORDERS_PAYMENT_STATUS_MANAGE);
        abort_unless($this->demo->configured() && $order->is_demo, 404);
        if ($next === PaymentStatus::Refunded) {
            throw ValidationException::withMessages(['payment_status' => 'Refund settlement is deferred.']);
        }
        if (blank($reason)) {
            throw ValidationException::withMessages(['payment_reason' => 'An internal reason is required.']);
        }

        return DB::transaction(function () use ($actor, $order, $next, $lockVersion, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['order' => 'This Order changed. Refresh before trying again.']);
            }
            $previous = $locked->payment_status;
            if ($previous === $next) {
                return $locked;
            }
            $attributes = ['payment_status' => $next, 'lock_version' => $locked->lock_version + 1];
            if ($next === PaymentStatus::Paid && $locked->receipt_reference === null) {
                $attributes += ['paid_at' => now('UTC'), 'receipt_reference' => OrderNumber::receipt()];
            }
            $locked->forceFill($attributes)->save();
            $locked->paymentEvents()->create(['previous_status' => $previous->value, 'new_status' => $next->value, 'actor_id' => $actor->id, 'reason' => $reason, 'created_at' => now('UTC')]);
            $this->audit->handle('order.payment-status-changed', $locked, $actor, ['payment_status' => $previous->value], ['payment_status' => $next->value, 'receipt_reference' => $locked->receipt_reference], PermissionRegistry::ORDERS_PAYMENT_STATUS_MANAGE, $reason);

            return $locked->refresh();
        });
    }
}
