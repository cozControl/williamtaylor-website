<?php

use App\Domain\SiteContent\Support\EvidenceDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

EvidenceDatabaseGuard::assertDisposable((string) config('database.connections.sqlite.database'));

$pageId = $argv[1] ?? null;
if (! is_string($pageId) || $pageId === '') {
    fwrite(STDERR, "A disposable About page ID is required.\n");
    exit(2);
}

$indexKey = 'public-page:index:'.$pageId;
$projectionKeys = Cache::get($indexKey, []);
$projectionKeys = is_array($projectionKeys)
    ? array_values(array_filter($projectionKeys, static fn (mixed $key): bool => is_string($key) && $key !== ''))
    : [];
$presentKeys = array_values(array_filter($projectionKeys, static fn (string $key): bool => Cache::has($key)));

echo json_encode([
    'page_id' => $pageId,
    'index_key_hash' => hash('sha256', $indexKey),
    'projection_key_hashes' => array_map(static fn (string $key): string => hash('sha256', $key), $projectionKeys),
    'projection_count' => count($projectionKeys),
    'present_count' => count($presentKeys),
    'built' => $projectionKeys !== [] && count($presentKeys) === count($projectionKeys),
    'cache_store' => config('cache.default'),
], JSON_THROW_ON_ERROR);
