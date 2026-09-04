<?php

namespace Tests\Feature\Demo;

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Orders\Actions\AddOrderNote;
use App\Domain\Orders\Actions\ChangeOrderPaymentStatus;
use App\Domain\Orders\Actions\CreateDemoOrder;
use App\Domain\Orders\Actions\TransitionOrder;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Enums\PaymentStatus;
use App\Domain\Orders\Support\OrderMoney;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class OrderFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        app(ProvisionRegisteredAccess::class)->handle();
    }

    public function test_money_uses_deterministic_integer_minor_units(): void
    {
        $this->assertSame(7500, OrderMoney::lineTotal(3, 2500));
        $this->expectException(\InvalidArgumentException::class);
        OrderMoney::lineTotal(0, 2500);
    }

    public function test_creation_calculates_totals_snapshots_history_audit_and_is_idempotent(): void
    {
        $actor = $this->super();
        $data = $this->data();
        $first = app(CreateDemoOrder::class)->handle($actor, $data);
        $second = app(CreateDemoOrder::class)->handle($actor, [...$data, 'customer_name' => 'Ignored duplicate']);

        $this->assertSame($first->id, $second->id);
        $this->assertMatchesRegularExpression('/^WT-\d{4}-[A-Z0-9]{10}$/', $first->order_number);
        $this->assertSame(5000, $first->subtotal_minor);
        $this->assertSame(5000, $first->total_minor);
        $this->assertSame('Snapshot Jacket', $first->items->first()->product_name);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('order_status_events', ['order_id' => $first->id, 'new_status' => 'new']);
        $this->assertDatabaseHas('audit_records', ['resource_identifier' => $first->id, 'action' => 'order.created']);
    }

    public function test_controlled_transitions_are_stale_safe_and_history_producing(): void
    {
        $actor = $this->super();
        $order = app(CreateDemoOrder::class)->handle($actor, $this->data());
        $confirmed = app(TransitionOrder::class)->handle($actor, $order, OrderStatus::Confirmed, 1);
        $this->assertSame(OrderStatus::Confirmed, $confirmed->status);
        $this->assertSame(2, $confirmed->lock_version);

        try {
            app(TransitionOrder::class)->handle($actor, $confirmed, OrderStatus::InPreparation, 1);
            $this->fail('A stale transition should fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('order_status_events', 2);
        }
    }

    public function test_invalid_skip_and_cancellation_without_reason_cause_zero_mutation(): void
    {
        $actor = $this->super();
        $order = app(CreateDemoOrder::class)->handle($actor, $this->data());
        foreach ([[OrderStatus::Ready, null], [OrderStatus::Cancelled, null]] as [$next, $reason]) {
            try {
                app(TransitionOrder::class)->handle($actor, $order, $next, 1, $reason);
                $this->fail('Invalid transition should fail.');
            } catch (ValidationException) {
                $this->assertSame(OrderStatus::New, $order->fresh()->status);
            }
        }
        $this->assertDatabaseCount('order_status_events', 1);
    }

    public function test_internal_notes_are_plain_immutable_and_never_in_customer_documents(): void
    {
        $actor = $this->super();
        $order = app(CreateDemoOrder::class)->handle($actor, $this->data());
        app(AddOrderNote::class)->handle($actor, $order, 'Private fitting detail');
        $this->actingAs($actor)->get(route('admin.orders.summary', $order))->assertOk()->assertDontSee('Private fitting detail');
        $this->expectException(ValidationException::class);
        app(AddOrderNote::class)->handle($actor, $order, '<b>unsafe</b>');
    }

    public function test_paid_manual_status_issues_one_stable_demo_receipt_and_refund_is_deferred(): void
    {
        $actor = $this->super();
        $order = app(CreateDemoOrder::class)->handle($actor, $this->data());
        $paid = app(ChangeOrderPaymentStatus::class)->handle($actor, $order, PaymentStatus::Paid, 1, 'Manual demonstration confirmation');
        $reference = $paid->receipt_reference;
        $this->assertNotNull($reference);
        $this->actingAs($actor)->get(route('admin.orders.receipt', $paid))->assertOk()->assertSee('Demo Receipt')->assertDontSee('Private fitting detail');
        $same = app(ChangeOrderPaymentStatus::class)->handle($actor, $paid, PaymentStatus::Paid, 2, 'Duplicate');
        $this->assertSame($reference, $same->receipt_reference);
        $this->expectException(ValidationException::class);
        app(ChangeOrderPaymentStatus::class)->handle($actor, $same, PaymentStatus::Refunded, 2, 'Unsupported refund');
    }

    public function test_routes_are_protected_and_view_only_user_cannot_mutate_or_view_receipt(): void
    {
        $order = app(CreateDemoOrder::class)->handle($this->super(), $this->data());
        $this->get(route('admin.orders.index'))->assertRedirect(route('login'));
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        Permission::findOrCreate(PermissionRegistry::ADMIN_ACCESS)->assignRole($role = Role::findOrCreate('Order Viewer'));
        Permission::findOrCreate(PermissionRegistry::ORDERS_VIEW)->assignRole($role);
        app(ControlledRoleMutation::class)->run(fn () => $viewer->assignRole($role));
        $this->actingAs($viewer)->get(route('admin.orders.show', $order))->assertOk();
        $this->post(route('admin.orders.transition', $order), ['status' => 'confirmed', 'lock_version' => 1])->assertForbidden();
        $this->get(route('admin.orders.receipt', $order))->assertForbidden();
        $this->assertSame(OrderStatus::New, $order->fresh()->status);
    }

    public function test_demo_inactive_hides_order_intake(): void
    {
        config(['demo.enabled' => false]);
        $this->actingAs($this->super())->get(route('admin.orders.index'))->assertNotFound();
    }

    private function super(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));

        return $user;
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return [
            'idempotency_key' => (string) str()->ulid(),
            'customer_name' => 'Fictional Customer',
            'customer_email' => 'fictional@example.test',
            'customer_telephone' => '+255700000000',
            'delivery_address' => '100 Demo Avenue',
            'items' => [['product_name' => 'Snapshot Jacket', 'quantity' => 2, 'unit_amount_minor' => 2500]],
        ];
    }
}
