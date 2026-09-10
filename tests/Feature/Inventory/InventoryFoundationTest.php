<?php

namespace Tests\Feature\Inventory;

use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductRevision;
use App\Domain\Catalogue\Actions\CreateProductVariant;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class InventoryFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    private ProductVariant $variant;

    private StockLocation $location;

    private InventoryLedgerService $ledger;

    private InventoryAvailabilityService $availability;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->actor = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->actor->assignRole(RoleRegistry::INVENTORY_MANAGER));
        $this->variant = $this->variant('Oxford Shirt');
        $this->location = StockLocation::main();
        $this->ledger = app(InventoryLedgerService::class);
        $this->availability = app(InventoryAvailabilityService::class);
    }

    public function test_zero_default_movements_and_catalogue_readiness_are_independent(): void
    {
        $before = app(CatalogueReadinessEvaluator::class)->evaluate($this->variant->product)->failureCodes;
        $this->assertSame(0, $this->availability->onHand($this->variant));
        $this->assertSame(0, $this->availability->availableToSell($this->variant));
        $this->assertFalse($this->availability->productHasAvailableStock($this->variant->product));
        $this->assertDatabaseCount('inventory_balances', 0);
        foreach ([[MovementType::Opening, 5], [MovementType::Receipt, 3], [MovementType::AdjustmentIn, 2], [MovementType::AdjustmentOut, 4]] as [$type, $quantity]) {
            $this->ledger->post($this->actor, $this->variant, $this->location, $type, $quantity, 'Test operation');
        }
        $this->assertSame(6, $this->availability->onHand($this->variant));
        $this->assertSame(6, $this->availability->availableToSell($this->variant));
        $this->assertTrue($this->availability->productHasAvailableStock($this->variant->product));
        $this->assertSame(6, (int) InventoryMovement::sum('quantity_delta'));
        $this->assertSame($before, app(CatalogueReadinessEvaluator::class)->evaluate($this->variant->product->fresh())->failureCodes);
        $this->assertDatabaseCount('inventory_movements', 4);
        $this->artisan('inventory:reconcile')->expectsOutputToContain('0 drifted')->assertSuccessful();
    }

    public function test_invalid_negative_and_stale_counts_leave_ledger_and_balance_unchanged(): void
    {
        $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Receipt, 3, 'Delivery');
        $operations = [
            fn () => $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::AdjustmentOut, 5, 'Damage'),
            fn () => $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Receipt, 1.5, 'Invalid units'),
            fn () => $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Opening, 2, 'Second opening'),
            fn () => $this->ledger->count($this->actor, $this->variant, $this->location, 2, 1, 'Stale count'),
            fn () => $this->ledger->count($this->actor, $this->variant, $this->location, 3, 3, 'No change'),
            fn () => $this->ledger->count($this->actor, $this->variant, $this->location, 2, 3, ''),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Invalid stock operation was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(3, $this->availability->onHand($this->variant));
        $this->assertDatabaseCount('inventory_movements', 1);
        $movement = $this->ledger->count($this->actor, $this->variant, $this->location, 0, 3, 'Count correction');
        $this->assertSame(-3, $movement->quantity_delta);
        $this->assertSame(0, $this->availability->onHand($this->variant));
    }

    public function test_idempotency_and_variant_location_isolation(): void
    {
        $other = $this->variant('Linen Shirt');
        $second = StockLocation::create(['code' => 'SECOND', 'name' => 'Second Store', 'active' => true, 'fulfillment_enabled' => true]);
        $first = $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Receipt, 5, 'Delivery', idempotencyKey: 'receipt:one', sourceType: 'delivery', sourceId: 'DEL-001');
        $retry = $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Receipt, 5, 'Delivery', idempotencyKey: 'receipt:one', sourceType: 'delivery', sourceId: 'DEL-001');
        $this->assertSame($first->id, $retry->id);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertSame(0, $this->availability->onHand($other));
        $this->assertSame(0, $this->availability->onHand($this->variant, $second));
        $this->ledger->post($this->actor, $this->variant, $second, MovementType::Receipt, 2, 'Delivery');
        $this->assertSame(2, $this->availability->onHand($this->variant, $second));
        $this->assertSame(5, $this->availability->onHand($this->variant));
        try {
            $this->ledger->post($this->actor, $other, $this->location, MovementType::Receipt, 5, 'Delivery', idempotencyKey: 'receipt:one');
            $this->fail('Conflicting key was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('idempotency_key', $exception->errors());
        }
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_movements_are_immutable_and_reconciliation_detects_drift_without_repair(): void
    {
        $movement = $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Opening, 4, 'Initial count');
        foreach ([fn () => $movement->update(['quantity_delta' => 8]), fn () => $movement->delete()] as $operation) {
            try {
                $operation();
                $this->fail('Ledger history was mutable.');
            } catch (\LogicException $exception) {
                $this->assertStringContainsString('cannot be', $exception->getMessage());
            }
        }
        InventoryBalance::query()->update(['on_hand' => 9]);
        $this->artisan('inventory:reconcile')->expectsOutputToContain('1 drifted')->assertFailed();
        $this->assertSame(9, $this->availability->onHand($this->variant));
        $this->assertSame(4, (int) InventoryMovement::sum('quantity_delta'));
    }

    public function test_unavailable_locations_and_archived_variants_do_not_sell(): void
    {
        $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Receipt, 2, 'Delivery');
        $this->location->update(['fulfillment_enabled' => false]);
        $this->assertSame(2, $this->availability->onHand($this->variant));
        $this->assertSame(0, $this->availability->availableToSell($this->variant));
        $this->assertFalse($this->availability->productHasAvailableStock($this->variant->product));
        $this->location->update(['fulfillment_enabled' => true]);
        $this->variant->update(['archived_at' => now()]);
        $this->assertSame(0, $this->availability->availableToSell($this->variant));
        $this->actingAs($this->actor)->get(route('admin.inventory.index', ['include_inactive' => 1, 'status' => 'out']))->assertOk()->assertSeeText('Oxford Shirt')->assertSeeText('Inactive - history retained');
        $this->get(route('admin.inventory.show', $this->variant))->assertOk()->assertSeeText('Movement history')->assertDontSee('name="operation"', false);
        $this->expectException(ValidationException::class);
        $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Receipt, 1, 'Invalid');
    }

    public function test_authorized_admin_operations_history_search_and_product_summary(): void
    {
        $this->actingAs($this->actor)->get(route('admin.inventory.index'))->assertOk()->assertSeeText('Oxford Shirt')->assertSeeText('Out of stock');
        $url = route('admin.inventory.store', $this->variant);
        $payload = ['location' => $this->location->id, 'quantity' => 5, 'reason' => 'Initial count', 'operation' => 'opening', 'idempotency_key' => (string) Str::uuid()];
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->post($url, [...$payload, 'operation' => 'receipt', 'quantity' => 2, 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->post($url, [...$payload, 'operation' => 'count', 'quantity' => 6, 'expected_on_hand' => 7, 'reason' => 'Stock count correction', 'idempotency_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->get(route('admin.inventory.show', $this->variant))->assertOk()->assertSeeText('Movement history')->assertSeeText('Stock count correction')->assertSeeText('-1')->assertDontSeeText('Delete Movement');
        $this->get(route('admin.inventory.index', ['search' => $this->variant->sku, 'status' => 'in']))->assertSeeText('Oxford Shirt');
        $this->get(route('admin.inventory.index', ['status' => 'out']))->assertDontSeeText('Oxford Shirt');
        $this->get(route('admin.products.edit', $this->variant->product))->assertOk()->assertSeeText('Stock by Variant')->assertSeeText('Manage Inventory');
        $this->post($url, [...$payload, 'quantity' => '2.5', 'reason' => ''])->assertSessionHasErrors(['quantity', 'reason']);
        $this->assertSame(6, $this->availability->onHand($this->variant));
        $this->assertDatabaseHas('audit_records', ['action' => 'inventory.adjustment_out']);
    }

    public function test_unauthorized_requests_and_service_calls_are_denied(): void
    {
        $this->get(route('admin.inventory.index'))->assertRedirect(route('login'));
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->get(route('admin.inventory.index'))->assertForbidden();
        $this->post(route('admin.inventory.store', $this->variant), [])->assertForbidden();
        $this->expectException(AuthorizationException::class);
        $this->ledger->post($user, $this->variant, $this->location, MovementType::Receipt, 1, 'Delivery');
    }

    public function test_projection_failure_rolls_back_the_movement_and_audit(): void
    {
        $auditCount = DB::table('audit_records')->count();
        InventoryBalance::saving(function (): void {
            throw new \RuntimeException('Projection unavailable');
        });
        try {
            $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Receipt, 2, 'Delivery');
            $this->fail('Failed projection was committed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Projection unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertSame($auditCount, DB::table('audit_records')->count());
    }

    public function test_view_only_inventory_permission_does_not_allow_posting(): void
    {
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $role = Role::create(['name' => 'Stock Viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(['admin.access', 'inventory.view']);
        app(ControlledRoleMutation::class)->run(fn () => $viewer->assignRole($role));
        $this->actingAs($viewer)->get(route('admin.inventory.show', $this->variant))->assertOk()->assertSeeText('Movement history')->assertDontSee('name="operation"', false);
        $this->post(route('admin.inventory.store', $this->variant), [])->assertForbidden();
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_idempotent_count_retry_and_balance_uniqueness(): void
    {
        $this->ledger->post($this->actor, $this->variant, $this->location, MovementType::Opening, 5, 'Initial count');
        $first = $this->ledger->count($this->actor, $this->variant, $this->location, 7, 5, 'Recount', idempotencyKey: 'count:one');
        $retry = $this->ledger->count($this->actor, $this->variant, $this->location, 7, 5, 'Recount', idempotencyKey: 'count:one');
        $this->assertSame($first->id, $retry->id);
        $this->assertSame(2, $first->quantity_delta);
        $this->assertSame(7, $this->availability->onHand($this->variant));
        $this->assertDatabaseCount('inventory_movements', 2);
        $this->expectException(UniqueConstraintViolationException::class);
        InventoryBalance::create(['variant_id' => $this->variant->id, 'stock_location_id' => $this->location->id, 'on_hand' => 0]);
    }

    private function variant(string $title): ProductVariant
    {
        $slug = Str::slug($title);
        $product = app(CreateProduct::class)->handle($this->actor, $slug, $slug);
        app(CreateProductRevision::class)->handle($this->actor, $product, 0, ['title' => $title]);
        $product->refresh();

        return app(CreateProductVariant::class)->handle($this->actor, $product, app(ProductStateFingerprint::class)->for($product), [], 'WT-'.strtoupper($slug));
    }
}
