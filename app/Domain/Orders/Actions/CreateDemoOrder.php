<?php

namespace App\Domain\Orders\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Support\OrderMoney;
use App\Domain\Orders\Support\OrderNumber;
use App\Models\User;
use App\Support\Demo\DemoMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class CreateDemoOrder
{
    public function __construct(private DemoMode $demo, private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): Order
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::ORDERS_CREATE);
        abort_unless($this->demo->configured(), 404);

        return DB::transaction(function () use ($actor, $data): Order {
            $existing = Order::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }

            $rawItems = $data['items'] ?? null;
            if (! is_array($rawItems)) {
                throw ValidationException::withMessages(['items' => 'At least one valid Order line is required.']);
            }
            $lines = [];
            foreach (array_values($rawItems) as $position => $line) {
                if (! is_array($line)) {
                    throw ValidationException::withMessages(['items' => 'Every Order line must be structured data.']);
                }
                $quantity = (int) ($line['quantity'] ?? 0);
                $unit = (int) ($line['unit_amount_minor'] ?? -1);
                $lines[] = [
                    'product_id' => $line['product_id'] ?? null,
                    'variant_id' => $line['variant_id'] ?? null,
                    'product_name' => (string) ($line['product_name'] ?? ''),
                    'variant_name' => $line['variant_name'] ?? null,
                    'sku' => $line['sku'] ?? null,
                    'options' => $line['options'] ?? null,
                    'quantity' => $quantity,
                    'unit_amount_minor' => $unit,
                    'line_total_minor' => OrderMoney::lineTotal($quantity, $unit),
                    'position' => $position,
                ];
            }
            $subtotal = array_sum(array_column($lines, 'line_total_minor'));
            $adjustment = (int) ($data['adjustment_minor'] ?? 0);
            if ($subtotal + $adjustment < 0) {
                throw ValidationException::withMessages(['adjustment_minor' => 'The adjustment cannot make the total negative.']);
            }

            do {
                $number = OrderNumber::generate();
            } while (Order::query()->where('order_number', $number)->exists());

            $order = Order::query()->create([
                'order_number' => $number,
                'is_demo' => true,
                'fixture_key' => $data['fixture_key'] ?? null,
                'customer_user_id' => $data['customer_user_id'] ?? null,
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'],
                'customer_telephone' => $data['customer_telephone'],
                'delivery_address' => $data['delivery_address'],
                'delivery_instructions' => $data['delivery_instructions'] ?? null,
                'currency' => strtoupper((string) ($data['currency'] ?? config('demo.order_currency', 'TZS'))),
                'subtotal_minor' => $subtotal,
                'adjustment_minor' => $adjustment,
                'total_minor' => $subtotal + $adjustment,
                'status' => OrderStatus::New,
                'payment_status' => PaymentStatus::Unpaid,
                'customer_note' => $data['customer_note'] ?? null,
                'created_by' => $actor->id,
                'source' => 'admin-demo',
                'idempotency_key' => $data['idempotency_key'],
                'lock_version' => 1,
            ]);
            $order->items()->createMany($lines);
            $order->statusEvents()->create(['previous_status' => null, 'new_status' => OrderStatus::New->value, 'actor_id' => $actor->id, 'reason' => 'Demo order created', 'created_at' => now('UTC')]);
            if (! empty($data['internal_note'])) {
                $order->notes()->create(['actor_id' => $actor->id, 'visibility' => 'internal', 'note' => $data['internal_note'], 'created_at' => now('UTC')]);
            }
            $this->audit->handle('order.created', $order, $actor, null, ['order_number' => $number, 'status' => 'new', 'item_count' => count($lines), 'total_minor' => $order->total_minor, 'currency' => $order->currency], PermissionRegistry::ORDERS_CREATE);

            return $order->load('items');
        });
    }
}
