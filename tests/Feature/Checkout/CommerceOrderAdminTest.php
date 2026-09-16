<?php

namespace Tests\Feature\Checkout;

use App\Domain\Admin\Navigation\AdminNavigationRegistry;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Checkout\Models\Order;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Orders\Actions\CreateDemoOrder;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Snippe\StartSnippePayment;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\CategoryOwner;
use Tests\TestCase;

final class CommerceOrderAdminTest extends TestCase
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
        $this->category = ProductCategory::query()->create(['collection_id' => CategoryOwner::for($this->manager->id)->id,
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

    public function test_authorization_navigation_and_production_demo_isolation(): void
    {
        [$order] = $this->order();
        config(['demo.enabled' => true, 'demo.allowed_environments' => ['testing']]);
        $demo = app(CreateDemoOrder::class)->handle($this->manager, [
            'idempotency_key' => 'admin-isolation', 'customer_name' => 'Demo Customer Only', 'customer_email' => 'demo-only@example.test',
            'customer_telephone' => '123456789', 'delivery_address' => 'Demo address',
            'items' => [['product_name' => 'Demo item', 'quantity' => 1, 'unit_amount_minor' => 100]],
        ]);
        config(['demo.enabled' => false, 'snippe.enabled' => false]);
        auth()->forgetGuards();
        $url = route('admin.commerce.orders.show', $order);
        $this->get($url)->assertRedirect(route('login'));
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $viewer->givePermissionTo('admin.access');
        $this->actingAs($viewer)->get(route('admin.commerce.orders.index'))->assertForbidden();
        $this->post(route('admin.commerce.orders.filters'), ['search' => 'Guest'])->assertForbidden();
        $this->get($url)->assertForbidden();
        $navigation = app(AdminNavigationRegistry::class);
        $this->assertNotContains('orders', array_column($navigation->visibleFor($viewer), 'key'));
        $viewer->givePermissionTo('orders.view');
        $this->assertContains('orders', array_column($navigation->visibleFor($viewer), 'key'));
        $this->assertNotContains('demo-orders', array_column($navigation->visibleFor($viewer), 'key'));
        $this->get(route('admin.commerce.orders.index'))->assertOk()->assertSee($order->order_number)->assertDontSee($demo->order_number)->assertDontSee('Demo Customer Only');
        $this->get($url)->assertOk()->assertSee('Private address')->assertDontSee('View Product')->assertDontSee('View Inventory')->assertDontSee($order->confirmation_reference);
        $this->get(route('admin.commerce.orders.show', $demo->id))->assertNotFound();
        $this->actingAs($this->manager)->get(route('admin.orders.index'))->assertNotFound();
        Http::assertSentCount(1);
    }

    public function test_storefront_snapshots_contacts_totals_and_private_evidence(): void
    {
        [$order, $payment, $variant] = $this->order();
        $variant->update(['sku' => 'CHANGED-LIVE-SKU', 'price_override_minor' => 100]);
        $product = $variant->product;
        $revision = $product->currentDraftRevision->replicate();
        $revision->title = 'Changed live title';
        $revision->created_at = now();
        $revision->revision_number++;
        $revision->checksum = hash('sha256', 'Changed live title');
        $revision->save();
        $product->update(['current_draft_revision_id' => $revision->id, 'archived_at' => now()]);
        $this->actingAs($this->manager)->get(route('admin.commerce.orders.index'))->assertOk()
            ->assertSee($order->order_number)->assertSee('Guest')->assertSee('guest@example.test')->assertSee('250,000')->assertSee('2 units · 1 item')
            ->assertSee('Pending confirmation')->assertSee('Unpaid')->assertSee('Unfulfilled')->assertDontSee('Private address')
            ->assertViewHas('metrics', ['Total Orders' => 1, 'Awaiting payment' => 1, 'Paid / confirmed' => 0, 'Awaiting fulfillment' => 0, 'Needs attention' => 0]);
        $response = $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('Payment Shirt')->assertSee('WT-PAYMENT-SHIRT')->assertSee('125,000')->assertSee('250,000')
            ->assertSee('Private address')->assertSee('+255712345678')->assertSee('guest@example.test')
            ->assertSee('sess_test')->assertSee('Order placed')->assertSee('Inventory reserved')->assertSee('View Product')->assertSee('View Inventory')
            ->assertDontSee('Changed live title')->assertDontSee('CHANGED-LIVE-SKU');
        foreach ([$order->confirmation_reference, $order->submission_key, $payment->return_reference, $payment->attempt_key, $payment->provider_checkout_url, 'test-key', 'test-signing-secret', 'Mark Paid', 'Delete Order', 'Edit Order'] as $private) {
            $response->assertDontSee($private);
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        Http::assertSentCount(1);
    }

    public function test_search_filters_pagination_and_bounded_index_queries(): void
    {
        [$first, , $variant] = $this->order();
        config(['snippe.enabled' => false]);
        app(InventoryLedgerService::class)->post($this->manager, $variant, StockLocation::main(), MovementType::Receipt, 50, 'Pagination fixture');
        for ($i = 1; $i <= 21; $i++) {
            $this->travel(1)->minutes();
            auth()->forgetGuards();
            $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertOk();
            $this->get('/checkout')->assertOk();
            $this->post('/checkout', ['submission' => array_key_last(session('checkout_attempts')), 'name' => 'Shopper '.$i, 'email' => 'shopper'.$i.'@example.test', 'phone' => '+255712345679', 'address' => 'Delivery '.$i, 'city' => 'Dar', 'region' => 'Dar'])->assertRedirect();
        }
        $latest = Order::query()->orderByDesc('placed_at')->firstOrFail();
        $this->actingAs($this->manager);
        $url = route('admin.commerce.orders.index');
        $response = $this->get($url)->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 22 && $orders->count() === 20 && $orders->first()->id === $latest->id);
        $response->assertSee('Shopper 21')->assertDontSee('guest@example.test');
        $this->get($url.'?page=2&payment_status=unpaid')->assertOk()->assertSee($first->order_number)->assertSee('payment_status=unpaid', false);
        foreach ([$first->order_number, '  gUeSt  ', 'GUEST@EXAMPLE.TEST', '+255 712 345 678'] as $term) {
            $this->post(route('admin.commerce.orders.filters'), ['search' => $term])->assertRedirect($url);
            $this->get($url)->assertOk()->assertSee($first->order_number)->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
        }
        $this->post(route('admin.commerce.orders.filters'), ['search' => 'Shopper', 'payment_status' => 'unpaid'])->assertRedirect($url.'?payment_status=unpaid');
        $this->get($url.'?page=2&payment_status=unpaid')->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 21 && $orders->count() === 1)->assertDontSee('search=Shopper', false);
        $this->post(route('admin.commerce.orders.filters'), ['clear' => '1'])->assertRedirect($url);
        foreach (['payment_status=unpaid', 'status=pending_confirmation', 'fulfillment_status=unfulfilled'] as $filter) {
            $this->get($url.'?'.$filter)->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 22);
        }
        $this->get($url.'?payment_status=paid')->assertOk()->assertSee('No Orders match these filters.');
        $this->get($url.'?to=2000-01-01')->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 0);
        $this->getJson($url.'?status=invalid')->assertUnprocessable();
        $this->getJson($url.'?from=2026-10-01&to=2026-09-01')->assertUnprocessable();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $allCount = count(DB::getQueryLog());
        $this->post(route('admin.commerce.orders.filters'), ['search' => $first->order_number])->assertRedirect($url);
        DB::flushQueryLog();
        $this->get($url)->assertOk();
        $singleCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($singleCount, $allCount, 'Query count must not grow with rows');
        Http::assertSentCount(1);
    }

    public function test_signed_paid_order_is_visible_with_consumed_reservation_issue_and_timeline(): void
    {
        [$order, $payment] = $this->order();
        $this->webhook($this->event())->assertOk();
        $this->actingAs($this->manager)->get(route('admin.commerce.orders.index', ['payment_status' => 'paid']))->assertOk()
            ->assertSee('Paid')->assertSee('Confirmed')->assertSee('Unfulfilled')
            ->assertViewHas('metrics', ['Total Orders' => 1, 'Awaiting payment' => 0, 'Paid / confirmed' => 1, 'Awaiting fulfillment' => 1, 'Needs attention' => 0]);
        $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertSee('Successful')->assertSee('Completed')->assertSee('pay_test')->assertSee('sess_test')
            ->assertSee('Consumed')->assertSee('Stock issued · 2 units')->assertSee('Inventory reservation consumed')->assertSee('Order confirmed')->assertDontSee('Check payment status');
        $this->assertDatabaseHas('inventory_movements', ['source_id' => $order->id, 'quantity_delta' => -2, 'type' => 'order_issue']);
        $this->post(route('admin.commerce.orders.payments.check', [$order, $payment]))->assertStatus(409);
        Http::assertSentCount(1);
    }

    public function test_attention_is_visible_without_false_payment_confirmation(): void
    {
        [$order] = $this->order();
        $this->webhook($this->event(['amount' => ['value' => 25000]]))->assertStatus(409);
        $this->actingAs($this->manager)->get(route('admin.commerce.orders.index', ['attention' => '1']))->assertOk()->assertSee($order->order_number)->assertSee('Needs attention')
            ->assertViewHas('metrics', ['Total Orders' => 1, 'Awaiting payment' => 1, 'Paid / confirmed' => 0, 'Awaiting fulfillment' => 0, 'Needs attention' => 1]);
        $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertSee('does not match this Order')->assertSee('Payment review required')->assertSee('Pending confirmation')->assertSee('Unpaid')->assertDontSee('Order confirmed');
        $this->assertDatabaseHas('inventory_reservations', ['order_id' => $order->id, 'status' => 'active']);
        Http::assertSentCount(1);
    }

    public function test_admin_check_reuses_bound_session_with_csrf_delay_config_and_expiry_feedback(): void
    {
        [$order, $payment] = $this->order();
        $url = route('admin.commerce.orders.payments.check', [$order, $payment]);
        $this->actingAs($this->manager);
        config(['snippe.enabled' => false]);
        $this->post($url)->assertRedirect()->assertSessionHas('status', 'Snippe payment checks are unavailable until the integration is enabled and configured.');
        config(['snippe.enabled' => true]);
        $this->post($url)->assertRedirect()->assertSessionHas('status', 'A payment check is already running or waiting for its next permitted check. Please try again later.');
        $payment->update(['next_reconcile_at' => now()->subMinute()]);
        $this->app['env'] = 'local';
        $this->post($url)->assertStatus(419);
        Http::fake(['https://api.snippe.sh/api/v1/sessions/sess_test' => function ($request) {
            $this->assertSame('GET', $request->method());
            $this->assertSame(0, DB::transactionLevel());

            return Http::response(['data' => ['reference' => 'sess_test', 'status' => 'expired', 'amount' => 250000, 'currency' => 'TZS']]);
        }]);
        $this->post($url, ['_token' => session()->token()])->assertRedirect()->assertSessionHas('status', 'Payment check finished. The latest verified status is shown below.');
        $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertSee('Payment expired')->assertSee('Unpaid')->assertSee('Released')->assertSee('Reservation released · 2 units')->assertDontSee('Stock issued');
        $this->assertDatabaseHas('inventory_reservations', ['order_id' => $order->id, 'status' => 'released']);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('commerce_payments', 1);
        $this->assertDatabaseCount('commerce_orders', 1);
        Http::assertSentCount(1);
    }

    public function test_empty_workspace_and_unbound_attempt_cannot_create_session_from_admin(): void
    {
        config(['demo.enabled' => false]);
        $this->actingAs($this->manager)->get(route('admin.commerce.orders.index'))->assertOk()->assertSee('No customer orders yet.')->assertDontSee('Create Demo Order');
        [$order, $payment] = $this->order(503);
        $this->actingAs($this->manager)->post(route('admin.commerce.orders.payments.check', [$order, $payment]))->assertStatus(409);
        $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertSee('Payment outcome is unresolved')->assertDontSee('Check payment status');
        Http::assertSentCount(1);
    }

    public function test_failed_attempt_history_and_payment_check_authority(): void
    {
        [$order, $failed] = $this->order(400);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => fn ($request) => Http::response(['data' => ['reference' => 'sess_retry', 'status' => 'pending', 'amount' => 250000, 'currency' => 'TZS', 'checkout_url' => 'https://snippe.me/checkout/retry', 'metadata' => $request['metadata']]], 201)]);
        app(StartSnippePayment::class)->start($order);
        $current = Payment::query()->where('active_order_id', $order->id)->sole();
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $viewer->givePermissionTo('admin.access');
        $url = route('admin.commerce.orders.payments.check', [$order, $current]);
        $this->actingAs($viewer)->post($url)->assertForbidden();
        $this->actingAs($this->manager)->get(route('admin.commerce.orders.show', $order))->assertOk()
            ->assertSeeInOrder(['Attempt 1 · Failed', 'Attempt 2 · Current'])->assertSee('sess_retry');
        $this->post(route('admin.commerce.orders.payments.check', [$order, $failed]))->assertStatus(409);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 401)]);
        $current->update(['next_reconcile_at' => now()->subMinute()]);
        $this->post($url)->assertRedirect()->assertSessionHas('status', 'Payment verification needs review. See the recorded payment information below.');
        $this->get(route('admin.commerce.orders.show', $order))->assertOk()->assertSee('Snippe access needs configuration review')->assertSee('Unpaid');
        $this->assertDatabaseCount('commerce_payments', 2);
        $this->assertDatabaseCount('commerce_orders', 1);
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
