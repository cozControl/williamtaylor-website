<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Support\Demo\DemoMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class TransitionOrder
{
    public function __construct(private DemoMode $demo, private RecordAuditEvent $audit) {}

    public function handle(User $actor, Order $order, OrderStatus $next, int $lockVersion, ?string $reason = null): Order
    {
        $permission = match ($next) {
            OrderStatus::Confirmed => PermissionRegistry::ORDERS_CONFIRM,
            OrderStatus::InPreparation => PermissionRegistry::ORDERS_PREPARE,
            OrderStatus::Ready => PermissionRegistry::ORDERS_MARK_READY,
            OrderStatus::Dispatched => PermissionRegistry::ORDERS_DISPATCH,
            OrderStatus::Delivered => PermissionRegistry::ORDERS_DELIVER,
            OrderStatus::Cancelled => PermissionRegistry::ORDERS_CANCEL,
            OrderStatus::New => throw ValidationException::withMessages(['status' => 'Cannot transition back to new.']),
        };
        Gate::forUser($actor)->authorize($permission);
        abort_unless($this->demo->configured() && $order->is_demo, 404);
        if ($next === OrderStatus::Cancelled && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A cancellation reason is required.']);
        }

        return DB::transaction(function () use ($actor, $order, $next, $lockVersion, $reason, $permission): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['order' => 'This Order changed. Refresh before trying again.']);
            }
            $previous = $locked->status;
            if (! $previous->canTransitionTo($next)) {
                throw ValidationException::withMessages(['status' => "Cannot move from {$previous->value} to {$next->value}."]);
            }
            $timestamps = match ($next) {
                OrderStatus::Confirmed => ['confirmed_at' => now('UTC')],
                OrderStatus::Ready => ['ready_at' => now('UTC')],
                OrderStatus::Dispatched => ['dispatched_at' => now('UTC')],
                OrderStatus::Delivered => ['delivered_at' => now('UTC')],
                OrderStatus::Cancelled => ['cancelled_at' => now('UTC'), 'cancellation_reason' => $reason],
                default => [],
            };
            $locked->forceFill([...$timestamps, 'status' => $next, 'lock_version' => $locked->lock_version + 1])->save();
            $locked->statusEvents()->create(['previous_status' => $previous->value, 'new_status' => $next->value, 'actor_id' => $actor->id, 'reason' => $reason, 'created_at' => now('UTC')]);
            $this->audit->handle("order.{$next->value}", $locked, $actor, ['status' => $previous->value], ['status' => $next->value], $permission, $reason);

            return $locked->refresh();
        });
    }
}
