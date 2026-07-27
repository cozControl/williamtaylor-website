<?php

use App\Domain\Factory\Services\FactoryContentInstaller;
use App\Domain\Factory\Services\FactoryMediaSynchronizer;
use App\Domain\Identity\Actions\AlignFoundationRegistry;
use App\Domain\SiteContent\Support\EvidenceDatabaseGuard;
use App\Models\User;
use Database\Seeders\FactoryIdentitySeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
EvidenceDatabaseGuard::assertDisposable((string) config('database.connections.sqlite.database'));
app(AlignFoundationRegistry::class)->apply('BE-4H-0 disposable browser evidence');
app(FactoryIdentitySeeder::class)->install(false);
$admin = User::query()->where('email', config('factory.users.administrator.email'))->firstOrFail();
app(FactoryContentInstaller::class)->apply($admin);
app(FactoryMediaSynchronizer::class)->apply($admin, 'storefront-8d99836ea-logo-3');
$sessionCookie = function (User $user): array {
    $store = app('session')->driver();
    $store->setId(Str::random(40));
    $store->start();
    $store->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
    $store->save();
    $name = (string) config('session.cookie');
    $prefixed = CookieValuePrefix::create($name, app('encrypter')->getKey()).$store->getId();

    return ['name' => $name, 'value' => app('encrypter')->encrypt($prefixed, false)];
};
$users = User::query()->orderBy('id')->get();
echo json_encode([
    'passwords_valid' => $users->every(fn (User $user): bool => Hash::check((string) config('factory.users.'.match ($user->email) {
        'be4h0.admin@example.test' => 'administrator',
        'be4h0.cms@example.test' => 'cms_manager',
        default => 'inventory_manager',
    }.'.password'), $user->password)),
    'sessions' => $users->mapWithKeys(fn (User $user): array => [$user->email => $sessionCookie($user)])->all(),
], JSON_THROW_ON_ERROR);
