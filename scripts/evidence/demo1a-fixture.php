<?php

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

abort_unless(app()->environment('testing') && config('demo.enabled'), 403);
$password = getenv('DEMO1A_PASSWORD');
if (! is_string($password) || strlen($password) < 12) {
    throw new RuntimeException('DEMO1A_PASSWORD is required for the isolated fixture.');
}

app(ProvisionRegisteredAccess::class)->handle();
$user = User::query()->create([
    'name' => 'DEMO-1A Content Manager',
    'email' => 'demo1a.cms@example.test',
    'password' => Hash::make($password),
]);
$user->markEmailAsVerified();
app(ControlledRoleMutation::class)->run(fn () => $user->assignRole(RoleRegistry::CMS_MANAGER));

$ensure = app(EnsureSiteContent::class);
foreach ([
    SiteContentTypeRegistry::PRIMARY_NAVIGATION,
    SiteContentTypeRegistry::FOOTER_NAVIGATION,
    SiteContentTypeRegistry::SITE_PROFILE,
] as $type) {
    $ensure->handle($user, $type);
}
$announcement = $ensure->handle($user, SiteContentTypeRegistry::ANNOUNCEMENT, null, 'Client demo announcement');
$media = MediaAsset::query()->create([
    'provider' => 'deterministic',
    'provider_asset_id' => 'demo1a-ready-image',
    'provider_public_id' => 'demo1a/ready-image',
    'provider_version' => '1',
    'resource_type' => 'image',
    'delivery_type' => 'upload',
    'format' => 'jpg',
    'mime_type' => 'image/jpeg',
    'original_filename' => 'demo-footer.jpg',
    'internal_title' => 'DEMO-1A footer image',
    'default_alt_text' => 'Tailored jacket prepared for the client demo',
    'width' => 1200,
    'height' => 800,
    'bytes' => 1000,
    'accessibility_classification' => 'informative',
    'is_decorative' => false,
    'state' => MediaAssetState::Ready,
    'confirmed_at' => now(),
    'uploaded_by' => $user->id,
]);

echo json_encode([
    'user_id' => $user->id,
    'email' => $user->email,
    'announcement_id' => $announcement->id,
    'media_id' => $media->id,
], JSON_THROW_ON_ERROR);
