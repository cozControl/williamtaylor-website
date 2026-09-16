<?php

// Disposable, local browser fixture. Never included by application routes.
use App\Domain\Homepage\Actions\UpdateHomepageHotSale;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\CollectionCatalogueFixture;

if (PHP_SAPI === 'cli-server') {
    $asset = realpath(__DIR__.'/../../public'.parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($asset && str_starts_with($asset, realpath(__DIR__.'/../../public').DIRECTORY_SEPARATOR) && is_file($asset)) {
        return false;
    }
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$runtime = realpath(getenv('COLLECTION_BROWSER_RUNTIME') ?: '');
if (! $runtime || ! str_starts_with($runtime, realpath(__DIR__.'/../../storage/app/test-runtime').DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('A disposable Collection runtime directory is required.');
}
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $runtime.'/catalogue.sqlite', 'session.driver' => 'file', 'session.files' => $runtime.'/sessions', 'session.secure' => false, 'cache.default' => 'array', 'app.url' => 'http://127.0.0.1:8143', 'snippe.enabled' => false]);
DB::purge();
Http::preventStrayRequests();
if (PHP_SAPI === 'cli' && ($argv[1] ?? '') === 'seed') {
    touch($runtime.'/catalogue.sqlite');
    Artisan::call('migrate', ['--force' => true]);
    app(ProvisionRegisteredAccess::class)->handle();
    $actor = User::factory()->create(['email_verified_at' => now()]);
    app(ControlledRoleMutation::class)->run(fn () => $actor->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));
    $fixture = new CollectionCatalogueFixture($actor);
    $formal = $fixture->collection('formal-wear');
    $casual = $fixture->collection('casual-wear');
    $empty = $fixture->collection('coming-soon');
    $shirts = $fixture->category($formal, 'shirts');
    $suits = $fixture->category($formal, 'suits');
    $polos = $fixture->category($casual, 'polos');
    for ($i = 1; $i <= 13; $i++) {
        $product = $fixture->product($formal, $shirts, 'tailored-shirt-'.$i, 'M', 12000000 + $i * 100000);
        if ($i === 1) {
            for ($j = 1; $j <= 18; $j++) {
                $extra = $fixture->category($formal, 'long-editorial-category-'.$j);
                $product->categories()->attach($extra->id, ['is_primary' => false, 'position' => $j]);
            }
        }
        app(InventoryLedgerService::class)->post($actor, $product->defaultVariant, StockLocation::main(), MovementType::Receipt, 5, 'Disposable catalogue stock');
    }
    $fixture->product($formal, $suits, 'tailored-suit', 'L', 25000000);
    $fixture->product($casual, $polos, 'casual-polo', 'XXL');
    $homepage = HomepageHero::query()->create([...HomepageHero::defaults(), 'id' => HomepageHero::SINGLETON_ID, 'explore_collections_managed' => true, 'explore_collection_1_id' => $formal->id, 'explore_collection_2_id' => $casual->id, 'new_arrivals_collection_id' => $formal->id, 'created_by' => $actor->id, 'updated_by' => $actor->id]);
    $homepage->refresh();
    app(UpdateHomepageHotSale::class)->handle($actor, $homepage, [...HomepageHero::hotSaleDefaults(), 'lock_version' => $homepage->lock_version, 'hot_sale_tile_1_destination' => 'collection:'.$formal->id], [1 => $fixture->image(), 2 => $fixture->image(), 3 => $fixture->image()]);
    file_put_contents($runtime.'/casual-collection', $casual->id);
    file_put_contents($runtime.'/actor', (string) $actor->id);
    file_put_contents($runtime.'/category', $shirts->id);
    echo "Seeded isolated Collection catalogue.\n";
    exit;
}
Route::middleware('web')->get('/__catalogue_fixture_login', function () use ($runtime) {
    Auth::loginUsingId((int) file_get_contents($runtime.'/actor'));

    return redirect()->route('admin.product-categories.index');
});
$app->handleRequest(Request::capture());
