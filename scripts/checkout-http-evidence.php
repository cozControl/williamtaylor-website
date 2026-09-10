<?php

use App\Domain\Cart\CartPresenter;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\ProductPrice;
use App\Domain\Checkout\Models\Order;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Bounded real HTTP-kernel/MySQL evidence. Every fixture and order rolls back.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['session.driver' => 'array', 'cache.default' => 'array']);
$db = DB::connection();
if ($db->getDriverName() !== 'mysql') {
    throw new RuntimeException('This evidence expects the local MySQL connection.');
}
$cart = app(CartPresenter::class);
$location = StockLocation::main();
$variant = null;
foreach (ProductVariant::query()->active()->limit(30)->get() as $candidate) {
    $summary = $cart->present([$candidate->id => 1]);
    if (($summary['lines'][0]['issue'] ?? '') === 'This item is currently out of stock.' && app(InventoryAvailabilityService::class)->onHand($candidate, $location) === 0) {
        $variant = $candidate;
        break;
    }
}
if (! $variant) {
    throw new RuntimeException('No eligible zero-stock fixture candidate; no changes made.');
}
$actor = User::query()->whereHas('roles', fn ($query) => $query->where('name', RoleRegistry::SUPER_ADMINISTRATOR))->firstOrFail();
$before = ['orders' => $db->table('commerce_orders')->count(), 'movements' => $db->table('inventory_movements')->count(), 'reservations' => $db->table('inventory_reservations')->count()];
$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$cookies = [];
$send = function (string $method, string $uri, array $data = []) use ($kernel, &$cookies) {
    request()->attributes->remove('cart.view');
    request()->attributes->remove('inventory.storefront');
    $request = Request::create('http://localhost'.$uri, $method, $data, $cookies, [], ['HTTP_ACCEPT' => 'text/html']);
    $response = $kernel->handle($request);
    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }
    $kernel->terminate($request, $response);

    return $response;
};
$check = function (bool $condition, string $message) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$db->beginTransaction();
try {
    app(InventoryLedgerService::class)->post($actor, $variant, $location, MovementType::Receipt, 5, 'COMMERCE-ORDER-1 rollback-only HTTP evidence');
    $movementCount = $db->table('inventory_movements')->count();
    $page = $send('GET', '/cart');
    preg_match('/name="csrf-token" content="([^"]+)"/', $page->getContent(), $token);
    $check(isset($token[1]), 'Missing CSRF token');
    $check($send('POST', '/checkout')->getStatusCode() === 419, 'CSRF did not reject');
    $check($send('POST', '/cart/items', ['_token' => $token[1], 'variant_id' => $variant->id, 'quantity' => 2])->getStatusCode() === 302, 'Add failed');
    $checkout = $send('GET', '/checkout');
    preg_match('/name="submission" value="([^"]+)"/', $checkout->getContent(), $submission);
    $check(isset($submission[1]), 'Missing checkout submission');
    $input = ['_token' => $token[1], 'submission' => $submission[1], 'name' => 'Disposable Guest', 'email' => 'checkout-evidence@example.test', 'phone' => '+255712345678', 'address' => 'Disposable', 'city' => 'Dar es Salaam', 'region' => 'Dar es Salaam'];
    $response = $send('POST', '/checkout', $input);
    $order = Order::query()->where('submission_key', hash('sha256', $submission[1]))->with('lines')->firstOrFail();
    $check($response->getStatusCode() === 302, 'Placement failed');
    $check($order->lines->sole()->quantity === 2, 'Wrong quantity');
    $check($order->lines->sole()->unit_price_minor === app(ProductPrice::class)->effectiveMinor($variant->product, $variant), 'Wrong price');
    $check(app(InventoryAvailabilityService::class)->onHand($variant) === 5, 'On-hand changed');
    $check(app(InventoryAvailabilityService::class)->availableToSell($variant) === 3, 'Availability incorrect');
    $check($db->table('inventory_movements')->count() === $movementCount, 'Checkout posted movement');
    $confirmation = $send('GET', '/order-confirmation/'.$order->confirmation_reference);
    $check($confirmation->getStatusCode() === 200 && str_contains($confirmation->getContent(), $order->order_number), 'Confirmation failed');
    $send('POST', '/checkout', $input);
    $check($db->table('commerce_orders')->count() === $before['orders'] + 1, 'Duplicate order');
    // Outer transaction deliberately defers Cart clearing. Test a competing bag.
    session()->put('commerce_cart', [$variant->id => 4]);
    session()->save();
    $checkout = $send('GET', '/checkout');
    $check(str_contains($checkout->getContent(), 'Review your bag'), 'Stale bag was accepted');
    $input['submission'] = array_key_last(session('checkout_attempts'));
    $send('POST', '/checkout', $input);
    $check($db->table('commerce_orders')->count() === $before['orders'] + 1, 'Oversell created order');
    // Independent connection proves the exact parent lock blocks a competitor.
    config(['database.connections.checkout_competitor' => config('database.connections.'.config('database.default'))]);
    $competitor = DB::connection('checkout_competitor');
    $competitor->statement('SET SESSION innodb_lock_wait_timeout = 1');
    $competitor->beginTransaction();
    $blocked = false;
    try {
        $competitor->table('products')->where('id', $variant->product_id)->lockForUpdate()->first();
    } catch (QueryException $error) {
        $blocked = ($error->errorInfo[1] ?? null) === 1205;
    } finally {
        $competitor->rollBack();
        DB::purge('checkout_competitor');
    }
    $check($blocked, 'Competing connection did not block on the inventory parent lock');
    echo "Independent MySQL connection blocked on the placement parent lock (1205).\n";
    echo json_encode(['http' => 'passed', 'csrf' => 419, 'quantity' => 2, 'on_hand' => 5, 'active_reserved' => 2, 'available' => 3, 'checkout_movements' => 0, 'duplicate' => 'same order', 'competing_quantity_4' => 'rejected', 'cart_clear' => 'deferred by outer transaction; committed clearing covered by isolated HTTP tests'], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $db->rollBack();
    $after = ['orders' => $db->table('commerce_orders')->count(), 'movements' => $db->table('inventory_movements')->count(), 'reservations' => $db->table('inventory_reservations')->count()];
    $check($before === $after, 'Rollback counts differ');
    $check(app(InventoryAvailabilityService::class)->onHand($variant, $location) === 0, 'Rollback stock differs');
    echo "Rollback restored persistent order, movement and reservation counts.\n";
}
