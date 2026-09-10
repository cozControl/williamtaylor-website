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
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Snippe\StartSnippePayment;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class SnippePaymentTest extends TestCase
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
        $this->get('/checkout')->assertOk()->assertSee('Continue to secure payment');
        $response = $this->post('/checkout', ['submission' => array_key_last(session('checkout_attempts')), 'name' => 'Guest', 'email' => 'guest@example.test', 'phone' => '+255712345678', 'address' => 'Private address', 'city' => 'Dar', 'region' => 'Dar']);
        $response->assertRedirect($failureStatus === null ? 'https://snippe.me/checkout/test' : route('checkout.confirmation', Order::query()->sole()->confirmation_reference));

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

    public function test_hosted_checkout_signed_success_issues_exactly_once_and_return_is_read_only(): void
    {
        [$order, $payment, $variant] = $this->order();
        $this->assertSame([], app(CartService::class)->lines());
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->get(route('snippe.return', $payment->return_reference).'?status=success')->assertOk()->assertSee("We're confirming your payment", false)->assertDontSee('Private address');
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->app['env'] = 'local';
        $this->postJson('/webhooks/snippe', $this->event())->assertUnauthorized();
        $this->webhook($this->event())->assertOk();
        $this->assertSame('completed', $payment->fresh()->status->value);
        $this->assertSame('confirmed', $order->fresh()->status->value);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('unfulfilled', $order->fresh()->fulfillment_status);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseHas('inventory_reservations', ['order_id' => $order->id, 'status' => 'consumed']);
        $this->assertDatabaseHas('inventory_movements', ['source_id' => $order->id, 'type' => 'order_issue', 'quantity_delta' => -2, 'actor_id' => null]);
        $this->webhook($this->event())->assertOk();
        $otherEvent = $this->event();
        $otherEvent['id'] = 'evt_second_delivery';
        $this->webhook($otherEvent)->assertOk();
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->get(route('snippe.return', $payment->return_reference))->assertOk()->assertSee('Payment received')->assertSee('Confirmed');
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertOk()->assertSee('Payment received');
        $this->actingAs($this->manager)->get(route('admin.inventory.show', $variant))->assertOk()->assertSee('System payment confirmation');
        Http::assertSentCount(1);
    }

    public function test_signed_mismatches_never_mutate_financial_truth(): void
    {
        [$order, $payment, $variant] = $this->order();
        foreach ([['amount' => ['value' => 25000]], ['amount' => ['currency' => 'USD']], ['session_reference' => 'sess_wrong'], ['metadata' => ['order_id' => 'wrong']]] as $index => $change) {
            $event = $this->event($change);
            $event['id'] = 'evt_mismatch_'.$index;
            $this->webhook($event)->assertStatus(409);
        }
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertNotNull($payment->fresh()->reconciliation_issue);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
        $this->assertDatabaseHas('snippe_webhook_receipts', ['outcome' => 'unknown_session']);
    }

    public function test_failed_attempt_keeps_stock_then_authenticated_expiry_releases_it(): void
    {
        [$order, $payment, $variant] = $this->order();
        $this->webhook($this->event([], 'payment.failed'))->assertOk();
        $this->assertSame('failed', $payment->fresh()->status->value);
        $this->assertSame('pay_test', $payment->fresh()->last_failure_reference);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
        Http::fake(['https://api.snippe.sh/api/v1/sessions/sess_test' => Http::response(['data' => ['reference' => 'sess_test', 'status' => 'expired', 'amount' => 250000, 'currency' => 'TZS']])]);
        $payment->refresh()->update(['next_reconcile_at' => now()->subMinute()]);
        $this->artisan('payments:reconcile-snippe')->assertExitCode(0);
        $this->assertSame('expired', $payment->fresh()->status->value);
        $this->assertSame('payment_expired', $order->fresh()->status->value);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertSame(5, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'released']);
        $event = $this->event();
        $event['id'] = 'evt_late_paid';
        $this->webhook($event)->assertStatus(409);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->post(route('snippe.retry', $order->confirmation_reference))->assertRedirect(route('checkout.confirmation', $order->confirmation_reference));
        $this->assertDatabaseCount('commerce_orders', 1);
    }

    public function test_missed_webhook_reconciles_completed_session_and_late_failure_cannot_reverse_it(): void
    {
        [$order, $payment, $variant] = $this->order();
        Http::fake(['https://api.snippe.sh/api/v1/sessions/sess_test' => Http::response(['data' => ['reference' => 'sess_test', 'status' => 'completed', 'amount' => 250000, 'currency' => 'TZS']])]);
        $payment->update(['next_reconcile_at' => now()->subMinute()]);
        $this->artisan('payments:reconcile-snippe')->assertExitCode(0);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->webhook($this->event([], 'payment.failed'))->assertOk();
        $this->assertSame('completed', $payment->fresh()->status->value);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->onHand($variant));
    }

    public function test_audit_failure_rolls_back_payment_order_issue_and_webhook_receipt(): void
    {
        [$order, $payment, $variant] = $this->order();
        AuditRecord::creating(function ($audit) {
            if ($audit->action === 'commerce.payment.completed') {
                throw new \RuntimeException('Forced completion failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->webhook($this->event());
            $this->fail('Expected rollback');
        } catch (\RuntimeException $error) {
            $this->assertSame('Forced completion failure', $error->getMessage());
        } finally {
            AuditRecord::flushEventListeners();
        }
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('snippe_webhook_receipts', 0);
        $this->assertDatabaseHas('inventory_reservations', ['status' => 'active']);
        $this->webhook($this->event())->assertOk();
    }

    public function test_timeout_reconciliation_finds_same_session_without_second_post(): void
    {
        [$order, $payment] = $this->order();
        // Simulate process loss after remote creation but before response persistence.
        DB::table('commerce_payments')->where('id', $payment->id)->update(['provider_session_reference' => null, 'provider_checkout_url' => null, 'next_reconcile_at' => null]);
        Http::fake(['*' => function ($request) use ($payment, $order) {
            $this->assertSame('GET', $request->method());

            return Http::response(['data' => [['reference' => 'sess_test', 'status' => 'pending', 'amount' => 250000, 'currency' => 'TZS', 'checkout_url' => 'https://snippe.me/checkout/test', 'metadata' => ['payment_attempt' => $payment->attempt_key, 'order_id' => $order->id]]]]);
        }]);
        $this->post(route('snippe.retry', $order->confirmation_reference))->assertRedirect('https://snippe.me/checkout/test');
        $this->assertDatabaseCount('commerce_payments', 1);
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertSame('sess_test', $payment->fresh()->provider_session_reference);
        Http::assertSentCount(1);
    }

    public function test_unknown_creation_is_not_retried_or_released_and_cancelled_session_releases(): void
    {
        [$order, $payment, $variant] = $this->order();
        DB::table('commerce_payments')->where('id', $payment->id)->update(['provider_session_reference' => null, 'provider_checkout_url' => null, 'next_reconcile_at' => null]);
        Http::fake(['*' => Http::response(['data' => []])]);
        $this->post(route('snippe.retry', $order->confirmation_reference))->assertRedirect(route('checkout.confirmation', $order->confirmation_reference));
        $this->assertSame('session_outcome_unknown', $payment->fresh()->reconciliation_issue);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseCount('commerce_payments', 1);
        Http::swap(new Factory);
        Http::fake(['*' => Http::response([], 401)]);
        $payment->refresh()->update(['next_reconcile_at' => null]);
        app(StartSnippePayment::class)->refresh($payment);
        $this->assertSame($order->id, $payment->fresh()->active_order_id, 'Discovery authentication failure cannot permit duplicate Session creation');
        DB::table('commerce_payments')->where('id', $payment->id)->update(['provider_session_reference' => 'sess_test', 'next_reconcile_at' => null]);
        Http::swap(new Factory);
        Http::fake(['*' => Http::response(['data' => ['reference' => 'sess_test', 'status' => 'cancelled', 'amount' => 250000, 'currency' => 'TZS']])]);
        $this->artisan('payments:reconcile-snippe')->assertExitCode(0);
        $this->assertSame('cancelled', $order->fresh()->status->value);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_definite_initiation_failure_can_retry_same_order_with_new_attempt(): void
    {
        [$order, $payment, $variant] = $this->order(400);
        $this->assertSame('failed', $payment->status->value);
        $this->assertNull($payment->active_order_id);
        $this->assertSame([], app(CartService::class)->lines());
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        Http::swap(new Factory);
        Http::fake(['*' => function ($request) {
            return Http::response(['data' => ['reference' => 'sess_retry', 'status' => 'pending', 'amount' => 250000, 'currency' => 'TZS', 'checkout_url' => 'https://snippe.me/checkout/retry', 'metadata' => $request['metadata']]], 201);
        }]);
        $this->post(route('snippe.retry', $order->confirmation_reference))->assertRedirect('https://snippe.me/checkout/retry');
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('commerce_payments', 2);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($variant));
    }

    public function test_ambiguous_initiation_retains_attempt_and_never_reposts(): void
    {
        [$order, $payment, $variant] = $this->order(503);
        $this->assertSame('processing', $payment->status->value);
        $this->assertNotNull($payment->request_started_at);
        $this->assertSame($order->id, $payment->active_order_id);
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertOk()->assertDontSee('private provider detail')->assertSee("We're checking your payment status", false);
        $this->post(route('snippe.retry', $order->confirmation_reference))->assertRedirect(route('checkout.confirmation', $order->confirmation_reference));
        Http::assertSentCount(1);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('commerce_payments', 1);
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
