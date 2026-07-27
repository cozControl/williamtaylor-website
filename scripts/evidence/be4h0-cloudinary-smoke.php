<?php

use App\Domain\Factory\Services\FactoryManifest;
use App\Domain\Media\Actions\ArchiveMediaAsset;
use App\Domain\Media\Actions\ReplaceMediaAsset;
use App\Domain\Media\Actions\RestoreMediaAsset;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaAssetVersion;
use App\Domain\SiteContent\Support\EvidenceDatabaseGuard;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
EvidenceDatabaseGuard::assertDisposable((string) config('database.connections.sqlite.database'));
$ca = getenv('PROJECT_CA_BUNDLE');
if (! is_string($ca) || ! is_file($ca)) {
    throw new RuntimeException('Trusted project CA bundle is required.');
}
$folder = trim((string) config('media.cloudinary.folder'), '/').'/factory/william-taylor-v1/';
$assets = MediaAsset::query()->where('collection_key', 'factory')->orderBy('provider_public_id')->get();
if ($assets->count() !== 43) {
    throw new RuntimeException('Expected 43 synchronized factory Media assets.');
}
$folderFacts = $assets->every(fn (MediaAsset $asset): bool => str_starts_with($asset->provider_public_id, $folder)
    && $asset->provider_asset_id !== '' && $asset->provider_version !== null
    && $asset->bytes > 0 && in_array($asset->resource_type->value, ['image', 'video'], true));
if (! $folderFacts) {
    throw new RuntimeException('Factory provider folder or facts are invalid.');
}
$provider = app(MediaProvider::class);
$providerExists = 0;
foreach ($assets as $asset) {
    if (! $provider->assetExists($asset->provider_asset_id)) {
        throw new RuntimeException('Provider asset verification failed.');
    }
    $providerExists++;
}
$logo = $assets->first(fn (MediaAsset $asset): bool => in_array('storefront-7a24ade71-logo-3', $asset->tags ?? [], true));
if (! $logo) {
    throw new RuntimeException('Synchronized smoke logo is missing.');
}
$transformationUrl = $provider->deliveryUrl($logo->provider_public_id, 'image', 'site_logo');
$transformationResponse = Http::withOptions(['verify' => $ca])->timeout(60)->get($transformationUrl);
if (! $transformationResponse->successful() || ! str_starts_with((string) $transformationResponse->header('Content-Type'), 'image/')) {
    throw new RuntimeException('Named site_logo transformation could not be verified.');
}
$manifest = app(FactoryManifest::class);
$initialEntry = collect($manifest->media())->firstWhere('logical_key', 'storefront-8d99836ea-logo-3');
$replacementEntry = collect($manifest->media())->firstWhere('logical_key', 'storefront-7a24ade71-logo-3');
$smokeId = strtolower((string) Str::ulid());
$initial = $provider->synchronizeFactorySource($initialEntry, $folder.'smoke/'.$smokeId.'/initial');
$actor = User::query()->findOrFail($logo->uploaded_by);
$smoke = DB::transaction(function () use ($actor, $initial, $smokeId): MediaAsset {
    $asset = MediaAsset::query()->create([
        'provider' => 'cloudinary', 'provider_asset_id' => $initial->assetId,
        'provider_public_id' => $initial->publicId, 'provider_version' => $initial->version,
        'resource_type' => $initial->resourceType, 'delivery_type' => $initial->deliveryType,
        'format' => $initial->format, 'mime_type' => $initial->mimeType,
        'original_filename' => $initial->filename, 'internal_title' => 'BE-4H-0 provider smoke '.$smokeId,
        'default_alt_text' => 'Provider smoke asset', 'rights_source' => 'client-supplied',
        'rights_notes' => 'Client supplied and licensed for production', 'tags' => ['provider-smoke', $smokeId],
        'collection_key' => 'provider-smoke', 'width' => $initial->width, 'height' => $initial->height,
        'duration_ms' => $initial->durationMs, 'bytes' => $initial->bytes, 'checksum' => $initial->checksum,
        'accessibility_classification' => 'informative', 'is_decorative' => false, 'state' => 'ready',
        'provider_metadata' => $initial->metadata, 'uploaded_by' => $actor->getKey(), 'confirmed_at' => now('UTC'),
    ]);
    MediaAssetVersion::query()->create([
        'media_asset_id' => $asset->getKey(), 'version_number' => 1,
        'provider_asset_id' => $initial->assetId, 'provider_public_id' => $initial->publicId,
        'provider_version' => $initial->version, 'resource_type' => $initial->resourceType,
        'format' => $initial->format, 'mime_type' => $initial->mimeType,
        'width' => $initial->width, 'height' => $initial->height, 'duration_ms' => $initial->durationMs,
        'bytes' => $initial->bytes, 'checksum' => $initial->checksum, 'provider_metadata' => $initial->metadata,
        'uploaded_by' => $actor->getKey(), 'is_current' => true, 'created_at' => now('UTC'),
    ]);

    return $asset;
});
$replacement = $provider->synchronizeFactorySource($replacementEntry, $folder.'smoke/'.$smokeId.'/replacement');
app(ReplaceMediaAsset::class)->handleVerified($actor, $smoke, $replacement, 'BE-4H-0 real provider replacement verification');
$smoke->refresh();
$versions = $smoke->versions()->orderBy('version_number')->get();
if ($versions->count() !== 2 || $versions[0]->is_current || ! $versions[1]->is_current || $versions[0]->provider_asset_id === $versions[1]->provider_asset_id) {
    throw new RuntimeException('Immutable replacement version verification failed.');
}
app(ArchiveMediaAsset::class)->handle($actor, $smoke, 'BE-4H-0 archive verification without provider deletion');
if (! $provider->assetExists($smoke->fresh()->provider_asset_id)) {
    throw new RuntimeException('Archive unexpectedly removed the provider binary.');
}
app(RestoreMediaAsset::class)->handle($actor, $smoke->fresh());
if ($smoke->fresh()->state->value !== 'ready') {
    throw new RuntimeException('Restore verification failed.');
}
echo json_encode([
    'factory_assets' => $assets->count(), 'provider_assets_verified' => $providerExists,
    'folder_prefix_verified' => true, 'named_transformation' => 'site_logo',
    'transformation_status' => $transformationResponse->status(),
    'smoke_versions' => $versions->count(), 'immutable_versions_verified' => true,
    'archive_preserved_provider_binary' => true, 'restore_state' => 'ready',
    'provider_deletion_performed' => false,
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT).PHP_EOL;
