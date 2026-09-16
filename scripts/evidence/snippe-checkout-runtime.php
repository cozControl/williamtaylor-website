<?php

// Disposable browser fixture only. Never loaded by the application or public router.
use App\Domain\Catalogue\Actions\AssignProductMedia;
use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductRevision;
use App\Domain\Catalogue\Actions\CreateProductVariant;
use App\Domain\Catalogue\Actions\SetDefaultProductVariant;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Support\ProductStateFingerprint;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Payments\MobileMoneyPayment;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

if (PHP_SAPI === 'cli-server') {
    $asset = realpath(__DIR__.'/../../public'.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($asset && str_starts_with($asset, realpath(__DIR__.'/../../public').DIRECTORY_SEPARATOR) && is_file($asset)) {
        return false;
    }
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$runtime = realpath(getenv('SNIPPE_CHECKOUT_RUNTIME') ?: '');
if (! $runtime || ! str_starts_with($runtime, realpath(__DIR__.'/../../storage/app/test-runtime').DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('A disposable checkout runtime directory is required.');
}
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $runtime.'/checkout.sqlite',
    'session.driver' => 'file', 'session.files' => $runtime.'/sessions', 'session.secure' => false,
    'cache.default' => 'array', 'app.url' => 'https://checkout.example.test',
    'snippe.enabled' => true, 'snippe.api_key' => 'fake-only', 'snippe.webhook_secret' => 'fake-only']);
DB::purge();
Http::preventStrayRequests();
Http::fake(['https://api.snippe.sh/*' => function ($request) use ($runtime) {
    if (DB::transactionLevel() !== 0) {
        throw new RuntimeException('Provider request held a transaction.');
    }
    $payment = Payment::query()->latest('id')->firstOrFail();
    file_put_contents($runtime.'/provider-calls.jsonl', json_encode(['method' => $request->method(), 'amount' => $payment->provider_amount_tzs, 'transaction_level' => DB::transactionLevel()]).PHP_EOL, FILE_APPEND);

    return Http::response(['data' => ['reference' => 'fake-'.$payment->id,
        'status' => $request->method() === 'POST' ? 'pending' : trim(file_get_contents($runtime.'/provider-state')),
        'amount' => ['value' => $payment->provider_amount_tzs, 'currency' => 'TZS']]], 200);
}]);

if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'seed') {
    touch($runtime.'/checkout.sqlite');
    Artisan::call('migrate', ['--force' => true]);
    app(ProvisionRegisteredAccess::class)->handle();
    $actor = User::factory()->create(['email_verified_at' => now()]);
    app(ControlledRoleMutation::class)->run(fn () => $actor->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));
    $product = app(CreateProduct::class)->handle($actor, 'checkout-shirt', 'checkout-shirt');
    app(CreateProductRevision::class)->handle($actor, $product, 0, ['title' => 'The Taylor Oxford Shirt', 'features' => []]);
    $product->refresh();
    $variant = app(CreateProductVariant::class)->handle($actor, $product, app(ProductStateFingerprint::class)->for($product), [], 'WT-BROWSER');
    $product->refresh();
    app(SetDefaultProductVariant::class)->handle($actor, $product, $variant, app(ProductStateFingerprint::class)->for($product));
    $image = MediaAsset::create(['provider_asset_id' => 'browser-image', 'provider_public_id' => 'browser/image', 'resource_type' => 'image', 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'shirt.jpg', 'internal_title' => 'Shirt', 'default_alt_text' => 'Oxford shirt', 'accessibility_classification' => 'informative', 'is_decorative' => false, 'state' => 'ready', 'bytes' => 1000, 'uploaded_by' => $actor->id, 'confirmed_at' => now()]);
    $product->refresh();
    app(AssignProductMedia::class)->handle($actor, $product, $image, app(ProductStateFingerprint::class)->for($product), 'primary');
    $category = ProductCategory::create(['name' => 'Shirts', 'slug' => 'shirts', 'is_visible' => true, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
    $product->categories()->attach($category->id, ['is_primary' => true, 'position' => 0]);
    $product->update(['base_price_minor' => 12500000, 'catalogue_status' => 'ready']);
    app(InventoryLedgerService::class)->post($actor, $variant, StockLocation::main(), MovementType::Receipt, 50, 'Disposable browser stock');
    file_put_contents($runtime.'/variant', $variant->id);
    file_put_contents($runtime.'/provider-state', 'pending');
    echo "Seeded disposable checkout.\n";
    exit;
}
if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'reconcile') {
    $payment = Payment::query()->latest('id')->firstOrFail();
    $payment->update(['next_reconcile_at' => null]);
    app(MobileMoneyPayment::class)->refresh($payment);
    echo $payment->fresh()->status->value;
    exit;
}
$app->handleRequest(Request::capture());
