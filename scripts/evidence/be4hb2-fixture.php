<?php

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\RevisionPayload;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$manifest = require database_path('factory/william-taylor-v1/media.php');
$keys = ['storefront-306170464-screenshot2026-07-10at215946' => 'hero', 'storefront-da608a583-image' => 'editorial_split'];
$user = DB::table('users')->value('id');
$assets = [];
foreach ($keys as $key => $type) {
    $m = collect($manifest['entries'])->firstWhere('logical_key', $key);
    $path = base_path($m['source']);
    [$w,$h] = getimagesize($path);
    $id = (string) Str::ulid();
    $public = trim((string) config('media.cloudinary.folder'), '/').'/'.$m['provider_suffix'];
    $now = now('UTC');
    DB::table('media_assets')->insert(['id' => $id, 'provider' => 'cloudinary', 'provider_asset_id' => 'evidence-'.$key, 'provider_public_id' => $public, 'provider_version' => '1', 'resource_type' => 'image', 'delivery_type' => 'upload', 'format' => pathinfo($path, PATHINFO_EXTENSION), 'mime_type' => $m['mime_type'], 'original_filename' => basename($path), 'internal_title' => $m['title'], 'default_alt_text' => $m['alt_text'], 'rights_source' => $m['rights'], 'rights_notes' => $m['usage'], 'width' => $w, 'height' => $h, 'bytes' => filesize($path), 'checksum' => $m['sha256'], 'accessibility_classification' => 'informative', 'is_decorative' => 0, 'state' => 'ready', 'uploaded_by' => $user, 'confirmed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('media_asset_versions')->insert(['id' => (string) Str::ulid(), 'media_asset_id' => $id, 'version_number' => 1, 'provider_asset_id' => 'evidence-version-'.$key, 'provider_public_id' => $public, 'provider_version' => '1', 'resource_type' => 'image', 'format' => pathinfo($path, PATHINFO_EXTENSION), 'mime_type' => $m['mime_type'], 'width' => $w, 'height' => $h, 'bytes' => filesize($path), 'checksum' => $m['sha256'], 'uploaded_by' => $user, 'is_current' => 1, 'created_at' => $now]);
    $assets[$type] = $id;
}
$state = DB::table('page_publication_states')->first();
$old = ContentRevision::findOrFail($state->current_public_revision_id);
$payload = $old->payload;
foreach ($payload['sections'] as &$section) {
    if ($section['type'] === 'hero') {
        $section['data']['desktop_media'] = ['asset_id' => $assets['hero'], 'alt_override' => 'William Taylor contemporary menswear', 'decorative' => false];
    }if ($section['type'] === 'editorial_split') {
        $section['data']['media'] = ['asset_id' => $assets['editorial_split'], 'alt_override' => 'A considered William Taylor menswear look', 'decorative' => false];
    }
}unset($section);
$revision = ContentRevision::create(['resource_type' => $old->resource_type, 'resource_id' => $old->resource_id, 'revision_number' => $old->revision_number + 1, 'schema_version' => $old->schema_version, 'payload' => $payload, 'checksum' => app(RevisionPayload::class)->checksum($payload), 'sanitizer_version' => $old->sanitizer_version, 'change_summary' => 'Disposable BE-4H-B.2 browser fixture', 'created_by' => $user, 'created_at' => now('UTC')]);
DB::table('page_publication_states')->where('id', $state->id)->update(['current_public_revision_id' => $revision->id, 'state_version' => $state->state_version + 1, 'updated_at' => now('UTC')]);
echo "BE4HB2_FIXTURE_READY\n";
