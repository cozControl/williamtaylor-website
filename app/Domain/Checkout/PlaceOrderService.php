<?php

namespace App\Domain\Checkout;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Cart\CartPresenter;
use App\Domain\Cart\CartService;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Checkout\Enums\OrderStatus;
use App\Domain\Checkout\Models\Order;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Payments\MobileMoneyPayment;
use App\Domain\Payments\Snippe\SnippeMoney;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlaceOrderService
{
    public function __construct(private CartService $cart, private CartPresenter $presenter, private InventoryReservationService $reservations) {}

    /** @param array<string, mixed> $summary */
    public static function reviewFingerprint(array $summary): string
    {
        return hash('sha256', json_encode(array_map(fn ($line) => [$line['variant_id'], $line['quantity'], $line['unit_price_minor']], $summary['lines']), JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    public function place(array $data, string $key, string $review): Order
    {
        $fingerprint = hash('sha256', json_encode([$data, $review], JSON_THROW_ON_ERROR));
        $cartLines = $this->cart->lines();
        ksort($cartLines);
        $cartFingerprint = hash('sha256', json_encode($cartLines, JSON_THROW_ON_ERROR));
        $identities = ProductVariant::query()->whereIn('id', array_keys($cartLines))->pluck('product_id', 'id');
        try {
            $order = DB::transaction(function () use ($data, $key, $review, $fingerprint, $cartFingerprint, $identities) {
                if ($existing = Order::query()->where('submission_key', $key)->lockForUpdate()->first()) {
                    return $this->replay($existing, $fingerprint, $cartFingerprint);
                }
                $requested = $this->cart->lines();
                if ($requested === []) {
                    throw ValidationException::withMessages(['cart' => 'Your bag is empty.']);
                }
                // All Products ascending, then all Variants ascending, MAIN, balances.
                // Product locks serialize missing balances and competing ledger writes too.
                Product::query()->whereIn('id', $identities->values()->unique())->orderBy('id')->lockForUpdate()->get();
                $variants = ProductVariant::query()->whereIn('id', array_keys($requested))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($variants as $variant) {
                    if ($identities->get($variant->id) !== $variant->product_id) {
                        throw ValidationException::withMessages(['cart' => 'Your bag has changed. Please review it.']);
                    }
                }
                $location = StockLocation::query()->where('code', 'MAIN')->sharedLock()->firstOrFail();
                InventoryBalance::query()->where('stock_location_id', $location->id)->whereIn('variant_id', array_keys($requested))->orderBy('variant_id')->lockForUpdate()->get();
                $summary = $this->presenter->present($requested);
                if (! $summary['is_checkout_ready']) {
                    throw ValidationException::withMessages(['cart' => 'One of the items in your bag is no longer available in the requested quantity. Please review your bag.']);
                }
                if (! hash_equals($review, self::reviewFingerprint($summary))) {
                    throw ValidationException::withMessages(['cart' => 'Your bag or prices have changed. Review the updated summary before placing your order.']);
                }
                if (config('snippe.enabled')) {
                    try {
                        SnippeMoney::tzs($summary['subtotal_minor'], 'TZS');
                    } catch (\InvalidArgumentException) {
                        throw ValidationException::withMessages(['cart' => 'Mobile Money requires a total of at least TZS 500 in whole shillings. Please review your bag.']);
                    }
                }
                $sequence = DB::table('commerce_order_numbers')->insertGetId([]);
                $order = Order::query()->create([
                    'order_number' => 'WT-'.now('UTC')->format('Y').'-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                    'confirmation_reference' => bin2hex(random_bytes(32)), 'submission_key' => $key,
                    'request_fingerprint' => $fingerprint, 'cart_fingerprint' => $cartFingerprint,
                    'customer_snapshot' => array_intersect_key($data, array_flip(['name', 'email', 'phone'])),
                    'delivery_snapshot' => array_intersect_key($data, array_flip(['name', 'phone', 'address', 'city', 'region', 'postal'])),
                    'currency' => 'TZS', 'subtotal_minor' => $summary['subtotal_minor'], 'total_minor' => $summary['subtotal_minor'],
                    'status' => OrderStatus::PendingConfirmation, 'payment_status' => 'unpaid', 'fulfillment_status' => 'unfulfilled', 'shipping_status' => 'pending', 'placed_at' => now('UTC'),
                ]);
                $total = 0;
                $storedLines = [];
                foreach ($summary['lines'] as $line) {
                    $unit = $line['unit_price_minor'];
                    $quantity = $line['quantity'];
                    if ($unit < 0 || $quantity < 1 || ($unit !== 0 && $quantity > intdiv(PHP_INT_MAX, $unit)) || $unit * $quantity > PHP_INT_MAX - $total) {
                        throw ValidationException::withMessages(['cart' => 'This quantity cannot currently be priced.']);
                    }
                    $total += $unit * $quantity;
                    $stored = $order->lines()->create(['product_id' => $variants[$line['variant_id']]->product_id, 'variant_id' => $line['variant_id'], 'product_title_snapshot' => $line['title'], 'sku_snapshot' => $line['sku'], 'options_snapshot' => $line['options'], 'unit_price_minor' => $unit, 'quantity' => $quantity, 'line_total_minor' => $unit * $quantity]);
                    $storedLines[] = $stored;
                }
                $this->reservations->reserveMany($storedLines, $location);
                if ($total !== $order->subtotal_minor || $total !== $order->total_minor) {
                    throw new \LogicException('Order totals do not reconcile.');
                }
                if (config('snippe.enabled')) {
                    app(MobileMoneyPayment::class)->prepare($order, $data['payer_phone'] ?? $data['phone']);
                }
                app(RecordAuditEvent::class)->handle('commerce.order.placed', $order, null, null, ['order_number' => $order->order_number, 'line_count' => count($summary['lines']), 'quantity' => $summary['item_count'], 'total_minor' => $total, 'currency' => 'TZS', 'inventory_status' => 'reserved']);

                return $order;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Order::query()->where('submission_key', $key)->first();
            if (! $existing) {
                throw $exception;
            }
            $order = $this->replay($existing, $fingerprint, $cartFingerprint);
        }
        // Also correct when embedded in a caller's outer transaction.
        DB::afterCommit(function () use ($order) {
            $lines = $this->cart->lines();
            ksort($lines);
            if (hash_equals($order->cart_fingerprint, hash('sha256', json_encode($lines, JSON_THROW_ON_ERROR)))) {
                $this->cart->clear();
            }
            request()->attributes->remove('inventory.storefront');
        });

        return $order;
    }

    private function replay(Order $order, string $fingerprint, string $cartFingerprint): Order
    {
        if (! hash_equals($order->request_fingerprint, $fingerprint) || ($this->cart->lines() !== [] && ! hash_equals($order->cart_fingerprint, $cartFingerprint))) {
            throw ValidationException::withMessages(['cart' => 'This submission was already used for different details. Review your bag and try again.']);
        }

        return $order;
    }
}
