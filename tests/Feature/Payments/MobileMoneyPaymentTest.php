<?php

namespace Tests\Feature\Payments;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Cart\CartService;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
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
use App\Domain\Payments\MobileMoneyPayment;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class MobileMoneyPaymentTest extends TestCase
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

    private function checkout(?int $error = null, array $details = [], ?\Closure $beforeSubmit = null): array
    {
        config(['snippe.enabled' => true, 'snippe.api_key' => 'direct-test-key', 'snippe.webhook_secret' => 'direct-signing-secret', 'app.url' => 'https://store.example.test']);
        Http::preventStrayRequests();
        Http::fake(['https://api.snippe.sh/v1/payments' => function ($request) use ($error) {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertDatabaseCount('commerce_orders', 1);
            $this->assertDatabaseCount('commerce_payments', 1);
            $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
            $this->assertSame([], app(CartService::class)->lines());
            $payment = Payment::query()->sole();
            $this->assertSame($payment->attempt_key, $request->header('Idempotency-Key')[0]);
            $this->assertLessThanOrEqual(30, strlen($payment->attempt_key));
            $this->assertSame($payment->request_snapshot, $request->data());
            $this->assertSame('mobile', $request['payment_type']);
            $this->assertSame(['amount' => 250000, 'currency' => 'TZS'], $request['details']);
            $this->assertSame('255712345678', $request['phone_number']);
            $this->assertSame(['firstname' => 'Guest', 'lastname' => 'Buyer', 'email' => 'guest@example.test'], $request['customer']);
            $this->assertSame(Order::query()->sole()->id, $request['metadata']['order_id']);
            $this->assertSame('https://store.example.test/webhooks/snippe', $request['webhook_url']);
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer direct-test-key'));
            if ($error === 0) {
                throw new ConnectionException('secret timeout body');
            }
            if ($error === -1) {
                return Http::response(['data' => ['unexpected' => 'private provider detail']]);
            }

            return $error ? Http::response(['message' => 'private provider detail'], $error) : Http::response(['data' => $this->evidence('pending')], 201);
        }]);
        $product = $this->product('Mobile Shirt', 'mobile-shirt', true);
        app(InventoryLedgerService::class)->post($this->manager, $product->defaultVariant, StockLocation::main(), MovementType::Receipt, 5, 'Direct payment fixture');
        auth()->forgetGuards();
        $this->postJson('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => 2])->assertOk();
        $this->get('/checkout')->assertOk()->assertSee('Place Order &amp; Pay', false)->assertSee('M-Pesa')->assertSee('Halotel')->assertDontSee('secure checkout');
        $beforeSubmit?->__invoke($product->defaultVariant);
        $response = $this->post('/checkout', array_replace(['submission' => array_key_last(session('checkout_attempts')), 'name' => 'Guest Buyer', 'email' => 'guest@example.test', 'phone' => '+255765432100', 'payer_phone' => '0712 345 678', 'address' => 'Private address', 'city' => 'Dar', 'region' => 'Dar'], $details));
        if ($response->isRedirect() && ! session('errors')) {
            $response->assertRedirect(route('checkout.confirmation', Order::query()->sole()->confirmation_reference));
        }

        return [Order::query()->first(), Payment::query()->first(), $product->defaultVariant, $response];
    }

    private function evidence(string $status, array $changes = []): array
    {
        return array_replace_recursive(['reference' => 'pi_mobile', 'status' => $status, 'amount' => ['value' => 250000, 'currency' => 'TZS'], 'expires_at' => now()->addHours(4)->toIso8601String()], $changes);
    }

    private function remote(string $state, array $changes = []): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['https://api.snippe.sh/v1/payments/pi_mobile' => function ($request) use ($state, $changes) {
            $this->assertSame('GET', $request->method());
            $this->assertSame(0, DB::transactionLevel());

            return Http::response(['data' => $this->evidence($state, $changes)]);
        }]);
    }

    private function webhook(string $state = 'completed', array $changes = [], string $id = 'evt_mobile', int $age = 0, bool $validSignature = true): TestResponse
    {
        $data = $this->evidence($state, $changes);
        unset($data['expires_at']);
        $raw = json_encode(['id' => $id, 'type' => 'payment.'.$state, 'api_version' => '2026-01-25', 'data' => $data], JSON_THROW_ON_ERROR);
        $timestamp = (string) (time() - $age);

        return $this->call('POST', '/webhooks/snippe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_X_WEBHOOK_SIGNATURE' => $validSignature ? hash_hmac('sha256', $timestamp.'.'.$raw, 'direct-signing-secret') : str_repeat('0', 64)], $raw);
    }

    public function test_checkout_commits_canonical_amount_attempt_and_stock_before_direct_http(): void
    {
        [$order, $payment] = $this->checkout(details: ['amount' => 1, 'currency' => 'USD', 'order_id' => 'attacker']);
        $this->assertSame('mobile_money', $payment->method);
        $this->assertSame('pending', $payment->status->value);
        $this->assertSame('pi_mobile', $payment->provider_payment_reference);
        $this->assertNull($payment->provider_session_reference);
        $this->assertSame('+255765432100', $order->customer_snapshot['phone']);
        $this->assertSame('255712345678', $payment->customer_phone);
        $raw = DB::table('commerce_payments')->first();
        $this->assertStringNotContainsString('255712345678', $raw->customer_phone);
        $this->assertStringNotContainsString('guest@example.test', $raw->request_snapshot);
        $this->assertArrayNotHasKey('request_snapshot', $payment->toArray());
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertDontSee('Order Received')->assertDontSee('Payment received')->assertSee('Payment is not yet confirmed.')->assertSee(json_encode(route('checkout.payment-status', $order->confirmation_reference)), false);
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertOk()->assertSee('Check your phone')->assertSee('Waiting for payment')->assertDontSee('255712345678')->assertDontSee('Private address')->assertDontSee('direct-test-key');
        Http::assertSentCount(1);
    }

    public function test_verified_completion_confirms_consumes_and_issues_exactly_once(): void
    {
        [$order, $payment, $variant] = $this->checkout();
        $this->remote('completed');
        $this->webhook()->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->status->value);
        $this->assertSame('unfulfilled', $order->fresh()->fulfillment_status);
        $this->assertSame('completed', $payment->fresh()->status->value);
        $this->assertNotNull($payment->fresh()->last_verified_at);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'consumed']);
        $this->assertDatabaseHas('inventory_movements', ['source_id' => $order->id, 'type' => 'order_issue', 'quantity_delta' => -2]);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->webhook()->assertOk();
        $this->webhook(id: 'evt_repeat')->assertOk();
        $this->assertDatabaseCount('inventory_movements', 2);
        Http::assertSentCount(1);
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertSee('Payment received');
    }

    public function test_invalid_and_stale_signatures_cannot_create_receipts_or_finalize(): void
    {
        [$order] = $this->checkout();
        $this->webhook(validSignature: false)->assertUnauthorized();
        $this->webhook(age: 301)->assertUnauthorized();
        $this->webhook(age: -301)->assertUnauthorized();
        $this->assertDatabaseCount('snippe_webhook_receipts', 0);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        Http::assertSentCount(1);
    }

    public static function mismatchCases(): array
    {
        return ['amount' => [['amount' => ['value' => 249999]]], 'currency' => [['amount' => ['currency' => 'USD']]], 'order' => [['metadata' => ['order_id' => 'wrong']]], 'reference' => [['reference' => 'pi_wrong']]];
    }

    #[DataProvider('mismatchCases')]
    public function test_webhook_mismatch_preserves_stock_and_order(array $changes): void
    {
        [$order, $payment, $variant] = $this->checkout();
        $this->webhook(changes: $changes)->assertStatus(409);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        if (! isset($changes['reference'])) {
            $this->assertSame('attention_required', $payment->fresh()->status->value);
            $this->remote('completed');
            app(MobileMoneyPayment::class)->refresh($payment, true);
            Http::assertNothingSent();
        }
    }

    #[DataProvider('mismatchCases')]
    public function test_authoritative_get_mismatch_cannot_finalize(array $changes): void
    {
        [$order, $payment] = $this->checkout();
        $this->remote('completed', $changes);
        $this->webhook()->assertStatus(409);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('attention_required', $payment->fresh()->status->value);
        $this->assertSame($changes['reference'] ?? 'pi_mobile', $payment->fresh()->last_evidence['reference']);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
    }

    public static function unpaidStates(): array
    {
        return [['failed'], ['expired'], ['voided']];
    }

    #[DataProvider('unpaidStates')]
    public function test_verified_final_unpaid_releases_without_issue_and_requires_fresh_checkout(string $state): void
    {
        [$order, $payment, $variant] = $this->checkout();
        $this->remote($state);
        $this->webhook($state)->assertOk();
        $this->assertSame($state, $payment->fresh()->status->value);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertSame(5, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'released']);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->getJson(route('checkout.payment-status', $order->confirmation_reference))->assertJsonPath('attempt_status', $state)->assertJsonPath('poll', false)->assertJsonPath('retry_allowed', false);
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertOk()->assertDontSee('Payment received')->assertDontSee('Try Mobile Money again')->assertDontSee('const poll =', false)->assertSee(match ($state) {
            'expired' => 'The payment request has expired.', 'voided' => 'The payment request was cancelled.', default => 'The payment was not completed.',
        });
        $this->post(route('snippe.retry', $order->confirmation_reference))->assertRedirect();
        $this->assertDatabaseCount('commerce_payments', 1);
        $this->webhook($state, id: 'evt_final_repeat')->assertOk();
    }

    public static function uncertainErrors(): array
    {
        return [['timeout', 0], ['malformed', -1], ['server', 503], ['rate_limit', 429], ['key_conflict', 422]];
    }

    #[DataProvider('uncertainErrors')]
    public function test_uncertain_requests_keep_attempt_reservation_and_safe_customer_output(string $label, int $error): void
    {
        [$order, $payment] = $this->checkout($error);
        $this->assertSame('attention_required', $payment->status->value, $label);
        $this->assertSame($order->id, $payment->active_order_id);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertSee('Checking your payment')->assertDontSee('Payment not completed')->assertDontSee('Order Received');
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertOk()->assertDontSee('private provider detail')->assertDontSee('Try Mobile Money again');
        $this->post(route('snippe.retry', $order->confirmation_reference))->assertRedirect();
        $this->assertDatabaseCount('commerce_payments', 1);
    }

    public function test_same_attempt_replay_uses_identical_body_key_and_never_changes_order(): void
    {
        [$order, $payment] = $this->checkout(503);
        $original = $payment->request_snapshot;
        Http::swap(new Factory);
        Http::fake(['https://api.snippe.sh/v1/payments' => function ($request) use ($payment, $original) {
            $this->assertSame($original, $request->data());
            $this->assertSame($payment->attempt_key, $request->header('Idempotency-Key')[0]);

            return Http::response(['data' => $this->evidence('pending')]);
        }]);
        $payment->update(['next_reconcile_at' => null]);
        app(MobileMoneyPayment::class)->refresh($payment);
        $this->assertSame('pi_mobile', $payment->fresh()->provider_payment_reference);
        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('commerce_payments', 1);
        Http::assertSentCount(1);
    }

    public function test_expired_idempotency_window_and_replay_auth_failure_never_permit_new_attempt(): void
    {
        [$order, $payment] = $this->checkout(503);
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([], 401)]);
        $payment->update(['next_reconcile_at' => null]);
        app(MobileMoneyPayment::class)->refresh($payment);
        $this->assertSame($order->id, $payment->fresh()->active_order_id);
        $payment->refresh()->update(['request_started_at' => now()->subHours(24), 'next_reconcile_at' => null]);
        app(MobileMoneyPayment::class)->refresh($payment);
        $this->assertSame('idempotency_window_elapsed', $payment->fresh()->reconciliation_issue);
        $this->assertSame($order->id, $payment->fresh()->active_order_id);
        Http::assertSentCount(1);
    }

    public function test_known_rejection_allows_deliberate_new_attempt_with_new_key(): void
    {
        [$order, $payment] = $this->checkout(400);
        $this->assertTrue($payment->retrySafe());
        Http::swap(new Factory);
        Http::fake(['*' => function ($request) use ($payment) {
            $this->assertNotSame($payment->attempt_key, $request->header('Idempotency-Key')[0]);

            return Http::response(['data' => $this->evidence('pending')]);
        }]);
        $this->post(route('snippe.retry', $order->confirmation_reference))->assertRedirect();
        $this->assertDatabaseCount('commerce_payments', 2);
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
    }

    public function test_receipt_survives_provider_failure_and_transition_rollback(): void
    {
        [$order, $payment] = $this->checkout();
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([], 503)]);
        $this->webhook()->assertStatus(409);
        $this->assertDatabaseCount('snippe_webhook_receipts', 1);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->remote('completed');
        AuditRecord::creating(function ($audit) {
            if ($audit->action === 'commerce.payment.completed') {
                throw new \RuntimeException('Forced completion failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->webhook();
            $this->fail('Expected rollback');
        } catch (\RuntimeException $error) {
            $this->assertSame('Forced completion failure', $error->getMessage());
        } finally {
            AuditRecord::flushEventListeners();
        }
        $this->assertDatabaseCount('snippe_webhook_receipts', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->webhook()->assertOk();
    }

    public function test_public_polling_requires_bearer_token_exposes_only_safe_state_and_stops(): void
    {
        [$order, $payment] = $this->checkout();
        $this->get('/checkout/payment-status/'.$order->id)->assertNotFound();
        $this->get('/checkout/payment-status/'.str_repeat('a', 64))->assertNotFound();
        $url = route('checkout.payment-status', $order->confirmation_reference);
        $this->getJson($url)->assertOk()->assertExactJson(['order_status' => 'pending_confirmation', 'payment_status' => 'unpaid', 'attempt_status' => 'pending', 'retry_allowed' => false, 'poll' => true])->assertHeader('Cache-Control', 'no-store, private');
        Http::assertSentCount(1);
        $this->remote('completed');
        $this->webhook()->assertOk();
        $this->getJson($url)->assertJsonPath('poll', false)->assertJsonPath('payment_status', 'paid');
    }

    public function test_admin_permissions_masked_phone_and_direct_status_check(): void
    {
        [$order, $payment] = $this->checkout();
        $url = route('admin.commerce.orders.show', $order);
        $this->get($url)->assertRedirect();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->actingAs($this->manager)->get($url)->assertOk()->assertSee('Mobile Money')->assertSee('pi_mobile')->assertDontSee('255712345678')->assertDontSee('direct-test-key');
        $this->remote('completed');
        $payment->update(['next_reconcile_at' => null]);
        $this->post(route('admin.commerce.orders.payments.check', [$order, $payment]))->assertRedirect();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_invalid_payer_phone_is_rejected_before_order_creation(): void
    {
        [, , , $response] = $this->checkout(details: ['payer_phone' => '+254712345678']);
        $response->assertSessionHasErrors('payer_phone');
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->assertDatabaseCount('commerce_payments', 0);
        Http::assertNothingSent();
    }

    public function test_pending_get_cannot_be_overridden_by_completed_webhook_or_browser_parameters(): void
    {
        [$order, $payment] = $this->checkout();
        $this->remote('pending');
        $this->webhook()->assertStatus(409);
        $this->get(route('checkout.confirmation', $order->confirmation_reference).'?status=paid')->assertOk();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
    }

    public function test_stock_is_revalidated_before_payment_and_demo_orders_are_unused(): void
    {
        [, , , $response] = $this->checkout(beforeSubmit: function ($variant) {
            app(InventoryLedgerService::class)->count($this->manager, $variant, StockLocation::main(), 1, 5, 'Changed stock before checkout');
        });
        $response->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->assertDatabaseCount('commerce_payments', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertSame(0, \App\Domain\Orders\Models\Order::query()->count());
        Http::assertNothingSent();
    }

    public function test_price_change_requires_checkout_review_before_any_payment(): void
    {
        [, , , $response] = $this->checkout(beforeSubmit: fn ($variant) => $variant->update(['price_override_minor' => 9900000]));
        $response->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('commerce_orders', 0);
        Http::assertNothingSent();
    }

    public function test_payment_preparation_failure_rolls_back_order_and_reservation_with_cart_intact(): void
    {
        Payment::creating(fn () => throw new \RuntimeException('Forced preparation failure'));
        $this->withoutExceptionHandling();
        try {
            $this->checkout();
            $this->fail('Expected preparation rollback');
        } catch (\RuntimeException $error) {
            $this->assertSame('Forced preparation failure', $error->getMessage());
        } finally {
            Payment::flushEventListeners();
        }
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertNotEmpty(app(CartService::class)->lines());
        Http::assertNothingSent();
    }

    public function test_receipt_conflict_and_late_completed_event_cannot_resurrect_released_stock(): void
    {
        [$order, $payment] = $this->checkout();
        $this->remote('expired');
        $this->webhook('expired')->assertOk();
        $this->webhook('completed')->assertStatus(409);
        $this->webhook('completed', id: 'evt_late')->assertStatus(409);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('incompatible_lifecycle', $payment->fresh()->reconciliation_issue);
        $this->assertSame('attention_required', $payment->fresh()->status->value);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'released']);
        Http::assertSentCount(1);
    }

    public function test_in_flight_operation_and_provider_rate_limit_prevent_parallel_calls(): void
    {
        [$order, $payment] = $this->checkout();
        $this->remote('completed');
        $payment->update(['io_lease_until' => now()->addMinute(), 'io_lease_token' => 'other-worker']);
        $this->webhook()->assertStatus(409);
        $this->assertSame('other-worker', $payment->fresh()->io_lease_token);
        Http::assertNothingSent();
        $payment->refresh()->update(['io_lease_until' => null, 'io_lease_token' => null, 'failure_code' => 'rate_limited', 'next_reconcile_at' => now()->addMinute()]);
        $this->webhook()->assertStatus(409);
        Http::assertNothingSent();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_missed_webhook_reconciles_direct_payment_without_another_post(): void
    {
        [$order, $payment] = $this->checkout();
        $this->remote('completed');
        $payment->update(['next_reconcile_at' => null]);
        $this->artisan('payments:reconcile-snippe')->assertExitCode(0);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('commerce_payments', 1);
        Http::assertSentCount(1);
    }

    public function test_checkout_below_minimum_is_rejected_before_order_or_payment(): void
    {
        [, , , $response] = $this->checkout(beforeSubmit: function ($variant) {
            $variant->update(['price_override_minor' => 10000]);
            $this->get('/checkout')->assertOk();
        });
        $response->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->assertDatabaseCount('commerce_payments', 0);
        Http::assertNothingSent();
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
