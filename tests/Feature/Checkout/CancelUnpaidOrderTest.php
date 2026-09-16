<?php

namespace Tests\Feature\Checkout;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Cart\CartService;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Checkout\CancelUnpaidOrderService;
use App\Domain\Checkout\Models\Order;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Snippe\StartSnippePayment;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class CancelUnpaidOrderTest extends TestCase
{
    private User $manager;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        // Isolated in-memory DB without unrelated migration down() paths.
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->category = ProductCategory::query()->create([
            'name' => 'Explore',
            'slug' => 'explore',
            'is_visible' => true,
            'position' => 0,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);
    }

    private function order(?int $failureStatus = null): array
    {
        config(['snippe.enabled' => true, 'snippe.api_key' => 'test-key', 'snippe.profile_id' => 'prof_test', 'snippe.webhook_secret' => 'test-signing-secret', 'app.url' => 'https://store.example.test']);
        Http::preventStrayRequests();
        Http::fake(['https://api.snippe.sh/api/v1/sessions' => function ($request) use ($failureStatus) {
            $this->assertSame(0, DB::transactionLevel(), 'External HTTP must run after commit');
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-key'));
            $this->assertLessThanOrEqual(30, strlen($request->header('Idempotency-Key')[0]));
            $this->assertSame('prof_test', $request['profile_id']);
            $this->assertSame(250000, $request['amount']);
            $this->assertSame(125000, $request['line_items'][0]['unit_price']);
            $this->assertSame(2, $request['line_items'][0]['quantity']);
            $this->assertFalse(isset($request['allowed_methods']));
            $this->assertFalse($request['allow_custom_amount']);
            $this->assertSame('Guest', $request['customer']['name']);
            $this->assertStringNotContainsString('confirmation', $request['redirect_url']);
            $this->assertSame('https://store.example.test/webhooks/snippe', $request['webhook_url']);
            if ($failureStatus !== null) {
                return Http::response(['message' => 'private provider detail'], $failureStatus);
            }

            return Http::response(['data' => ['reference' => 'sess_test', 'status' => 'pending', 'amount' => $request['amount'], 'currency' => 'TZS', 'checkout_url' => 'https://snippe.me/checkout/test', 'metadata' => $request['metadata'], 'expires_at' => now()->addHour()->toIso8601String()]], 201);
        }]);
        $product = $this->product('Payment Shirt', 'payment-shirt', true);
        app(InventoryLedgerService::class)->post($this->manager, $product->defaultVariant, StockLocation::main(), MovementType::Receipt, 5, 'Payment fixture');
        auth()->forgetGuards();
        $this->postJson('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 2])->assertOk();
        // Historical hosted records remain supported; new public checkout uses Mobile Money.
        config(['snippe.enabled' => false]);
        $this->get('/checkout')->assertOk()->assertSee('Place Order');
        $response = $this->post('/checkout', ['submission' => array_key_last(session('checkout_attempts')), 'name' => 'Guest', 'email' => 'guest@example.test', 'phone' => '+255712345678', 'address' => 'Private address', 'city' => 'Dar', 'region' => 'Dar']);
        $response->assertRedirect(route('checkout.confirmation', Order::query()->sole()->confirmation_reference));
        config(['snippe.enabled' => true]);
        app(StartSnippePayment::class)->start(Order::query()->sole());

        return [Order::query()->sole(), Payment::query()->sole(), $product->defaultVariant];
    }

    private function event(array $changes = [], string $type = 'payment.completed'): array
    {
        return ['id' => 'evt_test', 'type' => $type, 'api_version' => '2026-01-25', 'data' => array_replace_recursive(['reference' => 'pay_test', 'session_reference' => 'sess_test', 'status' => $type === 'payment.completed' ? 'completed' : 'failed', 'amount' => ['value' => 250000, 'currency' => 'TZS']], $changes)];
    }

    private function webhook(array $event): TestResponse
    {
        $raw = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();

        return $this->call('POST', '/webhooks/snippe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_X_WEBHOOK_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$raw, 'test-signing-secret')], $raw);
    }

    private function cancelOrder(Order $order, array $extra = []): TestResponse
    {
        return $this->actingAs($this->manager)->post(route('admin.commerce.orders.cancel', $order), $extra + ['cancellation_reason' => 'Customer requested cancellation', 'confirm_cancellation' => '1']);
    }

    private function remoteCancellation(string $final = 'cancelled', int $cancelStatus = 200, bool $webhookRace = false): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $reads = 0;
        Http::fake(['*' => function ($request) use (&$reads, $final, $cancelStatus, $webhookRace) {
            $this->assertSame(0, DB::transactionLevel());
            if ($request->method() === 'POST') {
                $this->assertStringEndsWith('/sessions/sess_test/cancel', $request->url());
                $this->assertLessThanOrEqual(30, strlen($request->header('Idempotency-Key')[0]));
                $this->assertSame('A payment operation is already running. Please check again shortly.', app(CancelUnpaidOrderService::class)->handle($this->manager, Order::query()->sole(), 'Concurrent cancellation'));
                if ($webhookRace) {
                    $this->webhook($this->event())->assertOk();
                }

                return Http::response([], $cancelStatus);
            }
            $this->assertSame('GET', $request->method());
            $reads++;

            return Http::response(['data' => ['reference' => 'sess_test', 'status' => $reads === 1 ? 'active' : $final, 'amount' => 250000, 'currency' => 'TZS']]);
        }]);
    }

    public function test_active_session_cancellation_releases_exact_units_and_is_idempotent(): void
    {
        [$order, $payment, $variant] = $this->order();
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->actingAs($this->manager)->get(route('admin.commerce.orders.show', $order))->assertOk()->assertSee('Cancel order');
        $this->get(route('admin.commerce.orders.cancel.confirm', $order))->assertOk()->assertSee('Confirm cancellation')->assertSee('Keep order');
        $this->remoteCancellation();
        $this->cancelOrder($order)->assertRedirect(route('admin.commerce.orders.show', $order))->assertSessionHas('status', 'Order '.$order->order_number.' was cancelled and 2 reserved units were released.');
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('unfulfilled', $order->fresh()->fulfillment_status);
        $this->assertSame('cancelled', $payment->fresh()->status->value);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertSame(5, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseHas('inventory_reservations', ['order_id' => $order->id, 'quantity' => 2, 'status' => 'released']);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('commerce_orders', ['id' => $order->id, 'cancelled_by' => $this->manager->id, 'cancellation_reason' => 'Customer requested cancellation']);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->cancelOrder($order, ['cancellation_reason' => 'Different retry reason'])->assertSessionHas('status', 'This order is already cancelled.');
        $this->assertSame(1, AuditRecord::query()->where('action', 'commerce.order.cancelled')->count());
        $this->assertSame('Customer requested cancellation', $order->fresh()->cancellation_reason);
        $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertSee('Cancelled')->assertSee('Customer requested cancellation')->assertSee($this->manager->name)->assertSee('Released')->assertSee('Order cancelled')->assertDontSee('>Cancel order</a>', false);
        $this->get(route('admin.commerce.orders.index', ['status' => 'cancelled']))->assertOk()->assertSee($order->order_number)->assertViewHas('metrics', ['Total Orders' => 1, 'Awaiting payment' => 0, 'Paid / confirmed' => 0, 'Awaiting fulfillment' => 0, 'Needs attention' => 0]);
        auth()->forgetGuards();
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertOk()->assertSee('This order has been cancelled.')->assertDontSee('Customer requested cancellation')->assertDontSee('Private address')->assertDontSee('Continue to secure payment');
        $this->post(route('snippe.retry', $order->confirmation_reference))->assertRedirect(route('checkout.confirmation', $order->confirmation_reference));
        $this->assertSame([], app(CartService::class)->lines());
        $this->assertDatabaseCount('commerce_payments', 1);
        Http::assertSentCount(3);
        $this->webhook($this->event())->assertStatus(409);
        $this->assertSame('incompatible_lifecycle', $payment->fresh()->reconciliation_issue);
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_paid_order_cannot_cancel_or_restock(): void
    {
        [$order, $payment, $variant] = $this->order();
        $this->webhook($this->event())->assertOk();
        $this->cancelOrder($order)->assertSessionHas('status', CancelUnpaidOrderService::PAID);
        $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertSee('has been paid and cannot be cancelled')->assertDontSee('>Cancel order</a>', false);
        $this->assertSame('confirmed', $order->fresh()->status->value);
        $this->assertSame('completed', $payment->fresh()->status->value);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'consumed']);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertSame(0, AuditRecord::query()->where('action', 'commerce.order.cancelled')->count());
        Http::assertSentCount(1);
    }

    public function test_provider_completed_race_reconciles_paid_instead_of_releasing(): void
    {
        [$order, $payment, $variant] = $this->order();
        $this->remoteCancellation('completed', 409);
        $this->cancelOrder($order)->assertSessionHas('status', CancelUnpaidOrderService::PAID);
        $this->assertSame('confirmed', $order->fresh()->status->value);
        $this->assertSame('completed', $payment->fresh()->status->value);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'consumed']);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertNull($order->fresh()->cancelled_at);
        $this->webhook($this->event())->assertOk();
        $this->assertDatabaseCount('inventory_movements', 2);
        Http::assertSentCount(3);
    }

    public function test_webhook_wins_during_provider_cancel_without_release(): void
    {
        [$order, $payment, $variant] = $this->order();
        $this->remoteCancellation('cancelled', 200, true);
        $this->cancelOrder($order)->assertSessionHas('status', CancelUnpaidOrderService::PAID);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('completed', $payment->fresh()->status->value);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'consumed']);
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->assertNull($order->fresh()->cancelled_at);
    }

    public function test_unclear_cancel_result_keeps_stock_and_allows_verified_retry(): void
    {
        [$order, $payment, $variant] = $this->order();
        $this->remoteCancellation('active', 503);
        $this->cancelOrder($order)->assertSessionHas('status', CancelUnpaidOrderService::UNKNOWN);
        $this->assertSame('pending_confirmation', $order->fresh()->status->value);
        $this->assertSame('cancellation_unconfirmed', $payment->fresh()->reconciliation_issue);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
        $this->assertDatabaseCount('inventory_movements', 1);
        $payment->refresh()->update(['next_reconcile_at' => now()->subMinute()]);
        $this->remoteCancellation('active', 200);
        $this->cancelOrder($order)->assertSessionHas('status', CancelUnpaidOrderService::UNKNOWN);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant), 'A successful transport response does not prove Session cancellation');
        $payment->refresh()->update(['next_reconcile_at' => now()->subMinute()]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => function ($request) {
            $this->assertSame('GET', $request->method());

            return Http::response(['data' => ['reference' => 'sess_test', 'status' => 'cancelled', 'amount' => 250000, 'currency' => 'TZS']]);
        }]);
        $this->cancelOrder($order)->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->availableToSell($variant));
        Http::assertSentCount(1);
    }

    public function test_network_failure_and_mismatched_evidence_never_release(): void
    {
        [$order, $payment, $variant] = $this->order();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::failedConnection()]);
        $this->cancelOrder($order)->assertSessionHas('status', CancelUnpaidOrderService::UNKNOWN);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertNull($payment->fresh()->io_lease_until);
        $payment->refresh()->update(['next_reconcile_at' => null]);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['data' => ['reference' => 'sess_test', 'status' => 'cancelled', 'amount' => 999, 'currency' => 'TZS']])]);
        $this->cancelOrder($order)->assertSessionHas('status', CancelUnpaidOrderService::REVIEW);
        $this->assertSame('pending_confirmation', $order->fresh()->status->value);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_definite_rejection_cancels_locally_without_rewriting_payment_history(): void
    {
        [$order, $payment, $variant] = $this->order(400);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $before = $payment->getAttributes();
        $this->cancelOrder($order)->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame($before, $payment->fresh()->getAttributes());
        $this->assertSame(5, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseCount('inventory_movements', 1);
        Http::assertNothingSent();
    }

    public function test_ambiguous_initialization_is_not_cancellable(): void
    {
        [$order, $payment, $variant] = $this->order(503);
        $this->cancelOrder($order)->assertSessionHas('status', CancelUnpaidOrderService::REVIEW);
        $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertDontSee('>Cancel order</a>', false);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        Http::assertSentCount(1);
    }

    public function test_authorization_confirmation_and_csrf_are_required(): void
    {
        [$order, $payment] = $this->order();
        $url = route('admin.commerce.orders.cancel', $order);
        auth()->forgetGuards();
        $this->post($url)->assertRedirect(route('login'));
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $viewer->givePermissionTo('admin.access', 'orders.view');
        $this->actingAs($viewer)->get(route('admin.commerce.orders.show', $order))->assertOk()->assertDontSee('>Cancel order</a>', false);
        $this->post($url, ['cancellation_reason' => 'Forged', 'confirm_cancellation' => '1'])->assertForbidden();
        $this->get(route('admin.commerce.orders.cancel.confirm', $order))->assertForbidden();
        $this->actingAs($this->manager)->postJson($url, ['confirm_cancellation' => '1'])->assertUnprocessable();
        $this->postJson($url, ['cancellation_reason' => 'Reason'])->assertUnprocessable();
        $this->postJson($url, ['cancellation_reason' => str_repeat('a', 501), 'confirm_cancellation' => '1'])->assertUnprocessable();
        $this->app['env'] = 'local';
        $this->cancelOrder($order)->assertStatus(419);
        $payment->update(['io_lease_until' => now()->addMinutes(5)]);
        $this->cancelOrder($order, ['_token' => session()->token()])->assertSessionHas('status', 'A payment operation is already running. Please check again shortly.');
        $this->assertSame('pending_confirmation', $order->fresh()->status->value);
        Http::assertSentCount(1);
    }

    public function test_cancellation_audit_failure_rolls_back_all_local_finality(): void
    {
        [$order, $payment, $variant] = $this->order();
        $this->remoteCancellation();
        AuditRecord::creating(function ($audit) {
            if ($audit->action === 'commerce.order.cancelled') {
                throw new \RuntimeException('Cancellation audit failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->cancelOrder($order);
            $this->fail('Expected audit rollback');
        } catch (\RuntimeException $error) {
            $this->assertSame('Cancellation audit failure', $error->getMessage());
        } finally {
            AuditRecord::flushEventListeners();
        }
        $this->assertSame('pending_confirmation', $order->fresh()->status->value);
        $this->assertSame('processing', $payment->fresh()->status->value);
        $this->assertNull($order->fresh()->cancelled_at);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame(0, AuditRecord::query()->whereIn('action', ['commerce.order.cancelled', 'commerce.payment.cancelled'])->count());
    }

    public function test_no_payment_order_cancels_locally_even_with_snippe_disabled(): void
    {
        config(['snippe.enabled' => false]);
        Http::preventStrayRequests();
        $product = $this->product('Unpaid Shirt', 'unpaid-shirt', true);
        $variant = $product->defaultVariant;
        app(InventoryLedgerService::class)->post($this->manager, $variant, StockLocation::main(), MovementType::Receipt, 5, 'Local cancellation fixture');
        auth()->forgetGuards();
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])->assertOk();
        $this->get('/checkout')->assertOk();
        $this->post('/checkout', ['submission' => array_key_last(session('checkout_attempts')), 'name' => 'Shopper', 'email' => 'shopper@example.test', 'phone' => '+255712345678', 'address' => 'Private delivery', 'city' => 'Dar', 'region' => 'Dar'])->assertRedirect();
        $order = Order::query()->sole();
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->cancelOrder($order)->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertSame(5, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseCount('commerce_payments', 0);
        $this->assertDatabaseCount('inventory_movements', 1);
        Http::assertNothingSent();
    }

    public function test_final_expired_session_is_preserved_while_staff_cancellation_is_recorded(): void
    {
        [$order, $payment, $variant] = $this->order();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => function ($request) {
            $this->assertSame('GET', $request->method());

            return Http::response(['data' => ['reference' => 'sess_test', 'status' => 'expired', 'amount' => 250000, 'currency' => 'TZS']]);
        }]);
        $this->cancelOrder($order)->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame('expired', $payment->fresh()->status->value);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertSame(5, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseCount('inventory_movements', 1);
        Http::assertSentCount(1);
    }

    public function test_inconsistent_stock_commitment_blocks_before_provider_io(): void
    {
        [$order] = $this->order();
        // Simulate damaged imported evidence; ordinary lifecycle APIs cannot make this edit.
        DB::table('inventory_reservations')->where('order_id', $order->id)->update(['quantity' => 1]);
        $this->cancelOrder($order)->assertSessionHas('status', 'Stock commitment requires review before this order can be cancelled.');
        $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertDontSee('>Cancel order</a>', false);
        $this->assertSame('pending_confirmation', $order->fresh()->status->value);
        $this->assertDatabaseCount('inventory_movements', 1);
        Http::assertSentCount(1);
    }

    private function product(string $title, string $slug, bool $active, array $extra = []): Product
    {
        $image = $this->image($slug, $title);
        $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => $title,
            'slug' => $slug,
            'short_description' => 'Canonical Product.',
            'currency' => 'TZS',
            'base_price' => '125,000',
            'primary_category_id' => $this->category->id,
            'primary_media_id' => $image->id,
            'media_alt' => [$image->id => $title],
            'gallery_media_ids' => [],
            'draft_variants' => [[
                'key' => 'none--none',
                'colour_key' => '',
                'size_key' => '',
                'label' => 'Default',
                'sku' => 'WT-'.strtoupper($slug),
                'price' => '',
            ]],
            'default_variant_key' => 'none--none',
            'status' => $active ? 'active' : 'hidden',
            ...$extra,
        ])->assertRedirect();

        return Product::query()->where('slug', $slug)->sole();
    }

    private function image(string $name, string $alt): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id,
            'provider_asset_id' => 'asset-'.$id,
            'provider_public_id' => 'homepage/'.$id,
            'resource_type' => MediaResourceType::Image,
            'format' => 'jpg',
            'mime_type' => 'image/jpeg',
            'original_filename' => $name.'.jpg',
            'internal_title' => $name,
            'default_alt_text' => $alt,
            'accessibility_classification' => AccessibilityClassification::Informative,
            'is_decorative' => false,
            'state' => MediaAssetState::Ready,
            'bytes' => 1000,
            'uploaded_by' => $this->manager->id,
            'confirmed_at' => now(),
        ]);
    }
}
