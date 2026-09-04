<?php

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Orders\Actions\CreateDemoOrder;
use App\Domain\Orders\Actions\TransitionOrder;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

abort_unless(app()->environment('testing') && config('demo.enabled'), 403);
$password = getenv('DEMO1B_PASSWORD');
if (! is_string($password) || strlen($password) < 12) {
    throw new RuntimeException('DEMO1B_PASSWORD is required.');
}

app(ProvisionRegisteredAccess::class)->handle();
$actor = User::query()->firstOrCreate(
    ['email' => 'demo1b.orders@example.test'],
    ['name' => 'DEMO-1B Order Manager', 'password' => Hash::make($password)],
);
$actor->markEmailAsVerified();
app(ControlledRoleMutation::class)->run(fn () => $actor->syncRoles([RoleRegistry::SUPER_ADMINISTRATOR]));

$targets = [
    'new' => OrderStatus::New,
    'confirmed' => OrderStatus::Confirmed,
    'preparation' => OrderStatus::InPreparation,
    'ready' => OrderStatus::Ready,
    'dispatched' => OrderStatus::Dispatched,
    'delivered' => OrderStatus::Delivered,
    'cancelled' => OrderStatus::Cancelled,
];
$viewer = User::query()->firstOrCreate(['email' => 'demo1b.viewer@example.test'], ['name' => 'DEMO-1B Order Viewer', 'password' => Hash::make($password)]);
$viewer->markEmailAsVerified();
$viewerRole = Role::findOrCreate('DEMO-1B Order Viewer');
$viewerRole->syncPermissions([Permission::findByName('admin.access'), Permission::findByName('orders.view')]);
app(ControlledRoleMutation::class)->run(fn () => $viewer->syncRoles([$viewerRole]));

$created = [];
foreach ($targets as $key => $target) {
    $fixtureKey = "demo1b-{$key}";
    $order = Order::query()->where('fixture_key', $fixtureKey)->first();
    if (! $order) {
        $order = app(CreateDemoOrder::class)->handle($actor, [
            'idempotency_key' => $fixtureKey,
            'fixture_key' => $fixtureKey,
            'customer_name' => 'Demo Customer '.str($key)->title(),
            'customer_email' => "{$key}@customers.example.test",
            'customer_telephone' => '+255 700 000 000',
            'delivery_address' => '100 Demo Avenue, Dar es Salaam',
            'delivery_instructions' => 'Fictional demonstration delivery.',
            'customer_note' => 'Thank you for this demonstration Order.',
            'internal_note' => 'Fixture-owned internal note.',
            'items' => [['product_name' => 'Demo Tailored Jacket', 'variant_name' => 'Navy / Medium', 'sku' => 'DEMO-JACKET-M', 'quantity' => 1, 'unit_amount_minor' => 25000000]],
        ]);
        $path = [OrderStatus::Confirmed, OrderStatus::InPreparation, OrderStatus::Ready, OrderStatus::Dispatched, OrderStatus::Delivered];
        if ($target === OrderStatus::New) {
            // Initial state is already the requested representative state.
        } elseif ($target === OrderStatus::Cancelled) {
            app(TransitionOrder::class)->handle($actor, $order, $target, $order->lock_version, 'Fictional customer cancellation');
        } else {
            foreach ($path as $step) {
                if ($step->value === $target->value || $order->status->canTransitionTo($step)) {
                    $order = app(TransitionOrder::class)->handle($actor, $order, $step, $order->lock_version);
                }
                if ($step === $target) {
                    break;
                }
            }
        }
    }
    $created[$key] = $order->order_number;
}

echo json_encode(['email' => $actor->email, 'view_email' => $viewer->email, 'orders' => $created], JSON_THROW_ON_ERROR);
