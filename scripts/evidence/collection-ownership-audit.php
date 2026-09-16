<?php

// Read-only data provenance. Contains public catalogue identities, never credentials.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$owned = Schema::hasColumn('product_categories', 'collection_id');
$columns = ['id', 'name', 'slug', 'parent_id', 'archived_at'];
if ($owned) {
    $columns[] = 'collection_id';
}
$snapshot = [
    'ownership_column' => $owned,
    'categories' => DB::table('product_categories')->select($columns)->orderBy('id')->get(),
    'assignments' => DB::table('product_category_assignments')->orderBy('product_id')->orderBy('product_category_id')->get(),
    'memberships' => DB::table('collection_products')->orderBy('id')->get(),
    'collections' => DB::table('collections')->select(['id', 'slug', 'catalogue_status', 'archived_at'])->orderBy('id')->get(),
];
$label = $owned ? 'after' : 'before';
$path = storage_path('logs/collections-ownership-'.$label.'.json');
if (is_file($path)) {
    throw new RuntimeException('Preserve the existing ownership snapshot; use it for comparison.');
}
file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo json_encode(['snapshot' => $label, 'categories' => count($snapshot['categories']), 'assignments' => count($snapshot['assignments']), 'memberships' => count($snapshot['memberships'])]).PHP_EOL;
