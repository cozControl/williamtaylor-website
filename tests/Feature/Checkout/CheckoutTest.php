<?php

namespace Tests\Feature\Checkout;

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
use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CheckoutTest extends TestCase
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

    private function checkout(int $quantity = 2): array
    {
        $product = $this->product('Checkout Shirt', 'checkout-shirt', true);
        app(InventoryLedgerService::class)->post($this->manager, $product->defaultVariant, StockLocation::main(), MovementType::Receipt, 5, 'Disposable checkout fixture');
        auth()->forgetGuards();
        $this->postJson('/cart/items', ['variant_id' => $product->defaultVariant->id, 'quantity' => $quantity])->assertOk();
        $this->get('/checkout')->assertOk()->assertSee('Contact Information')->assertSee('Checkout Shirt');

        return [$product, $this->input()];
    }

    private function input(): array
    {
        return ['submission' => array_key_last(session('checkout_attempts')), 'name' => 'Guest Shopper', 'email' => 'guest@example.test', 'phone' => '+255 712 345 678', 'address' => 'Disposable address', 'city' => 'Dar es Salaam', 'region' => 'Dar es Salaam', 'postal' => ''];
    }

    public function test_guest_http_order_snapshots_reserves_without_issuing_and_replays(): void
    {
        [$product, $data] = $this->checkout();
        $before = DB::table('inventory_movements')->count();
        $response = $this->post('/checkout', $data + ['total_minor' => 1, 'unit_price_minor' => 1]);
        $order = Order::query()->sole();
        $response->assertRedirect(route('checkout.confirmation', $order->confirmation_reference));
        $this->assertMatchesRegularExpression('/^WT-2026-\d{6,}$/', $order->order_number);
        $this->assertSame('pending_confirmation', $order->status->value);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('unfulfilled', $order->fulfillment_status);
        $this->assertSame('pending', $order->shipping_status);
        $this->assertSame('TZS', $order->currency);
        $this->assertSame('Guest Shopper', $order->customer_snapshot['name']);
        $this->assertSame('+255712345678', $order->customer_snapshot['phone']);
        $this->assertSame('Disposable address', $order->delivery_snapshot['address']);
        $this->assertSame(25000000, $order->total_minor);
        $this->assertSame($order->total_minor, $order->lines->sum('line_total_minor'));
        $this->assertSame(12500000, $order->lines->sole()->unit_price_minor);
        $this->assertSame([], app(CartService::class)->lines());
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($product->defaultVariant));
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($product->defaultVariant));
        $this->assertSame($before, DB::table('inventory_movements')->count());
        $this->assertDatabaseHas('inventory_reservations', ['order_id' => $order->id, 'quantity' => 2, 'status' => 'active']);
        $this->assertDatabaseCount('orders', 0);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->order_number)->assertSee('Order Received')->assertDontSee('Disposable address')->assertDontSee('guest@example.test');
        $this->get('/order-confirmation/'.str_repeat('a', 64))->assertNotFound();
        $this->get('/order-confirmation/'.$order->id)->assertNotFound();
        $this->post('/checkout', $data)->assertRedirect($response->headers->get('Location'));
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->postJson('/checkout', [...$data, 'name' => 'Conflicting'])->assertUnprocessable();
        $product->defaultVariant->update(['sku' => 'CHANGED', 'price_override_minor' => 100]);
        $this->assertSame('WT-CHECKOUT-SHIRT', $order->lines->sole()->sku_snapshot);
        $this->assertSame(12500000, $order->lines->sole()->unit_price_minor);
    }

    public function test_stale_stock_price_review_empty_and_invalid_identity(): void
    {
        [$product, $data] = $this->checkout(4);
        $this->postJson('/checkout', [...$data, 'phone' => '()()()()'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $variant = $product->defaultVariant;
        $variant->update(['price_override_minor' => 9900000]);
        $this->postJson('/checkout', $data)->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->get('/checkout')->assertOk()->assertSee('396,000');
        $data = $this->input();
        app(InventoryLedgerService::class)->count($this->manager, $variant, StockLocation::main(), 3, 5, 'Changed stock');
        $this->postJson('/checkout', $data)->assertUnprocessable();
        $this->assertSame([$variant->id => 4], app(CartService::class)->lines());
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->withSession(['commerce_cart' => [(string) Str::ulid() => 1]])->postJson('/checkout', $data)->assertUnprocessable();
        $this->withSession(['commerce_cart' => []])->postJson('/checkout', $data)->assertUnprocessable();
        $this->get('/checkout')->assertOk()->assertSee('Your cart is empty')->assertDontSee('id="wt-checkout-form"', false);
    }

    public function test_competing_checkout_cannot_reserve_last_units_and_changed_price_requires_review(): void
    {
        [$product, $data] = $this->checkout(4);
        $product->defaultVariant->update(['price_override_minor' => 9900000]);
        $this->postJson('/checkout', $data)->assertUnprocessable();
        $this->get('/checkout')->assertOk();
        $this->post('/checkout', $this->input())->assertRedirect();
        $this->assertSame(39600000, Order::query()->sole()->total_minor);
        $this->withSession(['commerce_cart' => [$product->defaultVariant->id => 2]])->get('/checkout')->assertOk()->assertSee('Review your bag');
        $this->postJson('/checkout', $this->input())->assertUnprocessable();
        $this->assertDatabaseCount('commerce_orders', 1);
        $this->assertDatabaseCount('inventory_reservations', 1);
        $this->assertSame(1, app(InventoryAvailabilityService::class)->availableToSell($product->defaultVariant));
    }

    public function test_failure_after_reservation_rolls_back_and_preserves_cart(): void
    {
        [$product, $data] = $this->checkout();
        AuditRecord::creating(function ($record) {
            if ($record->action === 'commerce.order.placed') {
                throw new \RuntimeException('Forced audit failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/checkout', $data);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $error) {
            $this->assertSame('Forced audit failure', $error->getMessage());
        } finally {
            AuditRecord::flushEventListeners();
        }
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->assertDatabaseCount('commerce_order_lines', 0);
        $this->assertDatabaseCount('inventory_reservations', 0);
        $this->assertSame([$product->defaultVariant->id => 2], app(CartService::class)->lines());
        $this->assertSame(5, app(InventoryAvailabilityService::class)->availableToSell($product->defaultVariant));
    }

    public function test_multiple_variants_locations_reservation_idempotency_and_csrf(): void
    {
        [$product, $data] = $this->checkout();
        $other = $this->product('Other Shirt', 'other-shirt', true);
        app(InventoryLedgerService::class)->post($this->manager, $other->defaultVariant, StockLocation::main(), MovementType::Receipt, 2, 'Other fixture');
        $secondLocation = StockLocation::query()->create(['code' => 'TEST', 'name' => 'Disposable location', 'active' => true, 'fulfillment_enabled' => true]);
        app(InventoryLedgerService::class)->post($this->manager, $other->defaultVariant, $secondLocation, MovementType::Receipt, 7, 'Other location');
        auth()->forgetGuards();
        $this->postJson('/cart/items', ['variant_id' => $other->defaultVariant->id, 'quantity' => 1])->assertOk();
        $this->get('/checkout')->assertOk();
        $data = $this->input();
        // Exercise the real CSRF middleware; Laravel bypasses it in testing mode.
        $this->app['env'] = 'local';
        $this->post('/checkout', $data)->assertStatus(419);
        $this->assertDatabaseCount('commerce_orders', 0);
        $this->post('/checkout', $data + ['_token' => session()->token()])->assertRedirect();
        $order = Order::query()->sole();
        $this->assertCount(2, $order->lines);
        $this->assertSame(37500000, $order->total_minor);
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($product->defaultVariant));
        $this->assertSame(1, app(InventoryAvailabilityService::class)->availableToSell($other->defaultVariant));
        $this->assertSame(7, app(InventoryAvailabilityService::class)->availableToSell($other->defaultVariant, $secondLocation));
        foreach ($order->lines as $line) {
            app(InventoryReservationService::class)->reserve($line, StockLocation::main());
        }
        $this->assertDatabaseCount('inventory_reservations', 2);
        $this->assertSame([], app(CartService::class)->lines());
        try {
            app(InventoryLedgerService::class)->count($this->manager, $product->defaultVariant, StockLocation::main(), 1, 5, 'Below commitments');
            $this->fail('Expected commitment protection');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('quantity', $error->errors());
        }
        $this->assertSame(5, app(InventoryAvailabilityService::class)->onHand($product->defaultVariant));
    }

    public function test_exact_option_variants_have_independent_snapshots_and_reservations(): void
    {
        $product = $this->product('Sized Shirt', 'sized-shirt', true, [
            'draft_sizes' => ['m' => ['name' => 'M'], 'l' => ['name' => 'L']],
            'draft_variants' => [
                ['key' => 'none--m', 'colour_key' => '', 'size_key' => 'm', 'label' => 'M', 'sku' => 'SIZE-M', 'price' => ''],
                ['key' => 'none--l', 'colour_key' => '', 'size_key' => 'l', 'label' => 'L', 'sku' => 'SIZE-L', 'price' => '150000'],
            ], 'default_variant_key' => 'none--m',
        ]);
        $variants = $product->variants->keyBy('sku');
        foreach (['SIZE-M' => 3, 'SIZE-L' => 2] as $sku => $stock) {
            app(InventoryLedgerService::class)->post($this->manager, $variants[$sku], StockLocation::main(), MovementType::Receipt, $stock, 'Option fixture');
            $this->postJson('/cart/items', ['variant_id' => $variants[$sku]->id, 'quantity' => $sku === 'SIZE-M' ? 2 : 1])->assertOk();
        }
        auth()->forgetGuards();
        $this->get('/checkout')->assertOk();
        $this->post('/checkout', $this->input())->assertRedirect();
        $order = Order::query()->sole();
        $this->assertSame(40000000, $order->total_minor);
        $lines = $order->lines->keyBy('sku_snapshot');
        $this->assertSame(['Size: M'], $lines['SIZE-M']->options_snapshot);
        $this->assertSame(['Size: L'], $lines['SIZE-L']->options_snapshot);
        foreach ($variants as $variant) {
            $this->assertSame(1, app(InventoryAvailabilityService::class)->availableToSell($variant));
        }
        $revision = $product->currentDraftRevision->replicate();
        $revision->title = 'Renamed Product';
        $revision->created_at = now();
        $revision->revision_number++;
        $revision->checksum = hash('sha256', 'Renamed Product');
        $revision->save();
        $product->update(['current_draft_revision_id' => $revision->id]);
        $this->get(route('checkout.confirmation', $order->confirmation_reference))->assertOk()->assertSee('Sized Shirt')->assertDontSee('Renamed Product');
        try {
            $lines['SIZE-M']->update(['unit_price_minor' => 1]);
            $this->fail('Snapshot changed');
        } catch (\LogicException $error) {
            $this->assertSame('Order line snapshots are immutable.', $error->getMessage());
        }
        try {
            DB::table('inventory_reservations')->where('order_id', $order->id)->update(['quantity' => 0]);
            $this->fail('Database accepted zero reservation quantity');
        } catch (QueryException $error) {
            $this->assertStringContainsString('Quantity must be positive', $error->getMessage());
        }
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
