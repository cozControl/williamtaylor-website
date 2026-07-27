<?php

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Actions\SaveSiteContentDraft;
use App\Domain\SiteContent\Services\SiteContentWorkflow;
use App\Domain\SiteContent\Support\EvidenceDatabaseGuard;
use App\Domain\SiteContent\Support\SiteContentFingerprint;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
EvidenceDatabaseGuard::assertDisposable((string) config('database.connections.sqlite.database'));
$password = getenv('BE4G_EVIDENCE_PASSWORD');
if (! is_string($password) || strlen($password) < 12) {
    throw new RuntimeException('BE4G_EVIDENCE_PASSWORD must be at least 12 characters.');
}
app(ProvisionRegisteredAccess::class)->handle();
$user = function (string $email, string $name, bool $cms = true) use ($password): User {
    $user = User::query()->create(['email' => $email, 'name' => $name, 'password' => Hash::make($password), 'email_verified_at' => now()]);
    $user->forceFill(['email_verified_at' => now()])->save();
    if ($cms) {
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole(RoleRegistry::CMS_MANAGER));
    }

    return $user;
};
$cms = $user('be4g1.cms@example.test', 'BE-4G.1 CMS Manager');
$user('be4g1.user@example.test', 'BE-4G.1 Ordinary User', false);
$navViewer = $user('be4g1.nav-viewer@example.test', 'Navigation Viewer', false);
$navViewer->givePermissionTo(['admin.access', 'navigation.view']);
$announcementViewer = $user('be4g1.announcement-viewer@example.test', 'Announcement Viewer', false);
$announcementViewer->givePermissionTo(['admin.access', 'announcements.view']);
$settingsViewer = $user('be4g1.settings-viewer@example.test', 'Settings Viewer', false);
$settingsViewer->givePermissionTo(['admin.access', 'settings.view']);
$ensure = app(EnsureSiteContent::class);
$save = app(SaveSiteContentDraft::class);
$workflow = app(SiteContentWorkflow::class);
$fingerprints = app(SiteContentFingerprint::class);
$primary = $ensure->handle($cms, 'primary_navigation');
$payload = ['items' => [['key' => 'collections', 'label' => 'Collections', 'link' => ['type' => 'internal_path', 'value' => '/collections'], 'new_tab' => false, 'visibility' => 'all', 'children' => [['key' => 'limited', 'label' => 'Limited Edition', 'link' => ['type' => 'internal_path', 'value' => '/limited-edition'], 'new_tab' => false, 'visibility' => 'all']]]]];
$save->handle($cms, $primary, $primary->current_draft_revision_id, $payload, 'Evidence navigation');
$primary = $primary->fresh();
$workflow->submit($cms, $primary, 'Ready for review', $fingerprints->for($primary));
$footer = $ensure->handle($cms, 'footer_navigation');
$announcement = $ensure->handle($cms, 'announcement', null, 'Appointment announcement');
$payload = $announcement->currentDraftRevision->payload;
$payload['message'] = 'Private appointments are available in Dar es Salaam.';
$payload['cta_label'] = 'Contact us';
$payload['cta'] = ['type' => 'internal_path', 'value' => '/contact'];
$save->handle($cms, $announcement, $announcement->current_draft_revision_id, $payload, 'Evidence announcement');
$announcement = $announcement->fresh();
$workflow->submit($cms, $announcement, 'Ready', $fingerprints->for($announcement));
$announcement = $announcement->fresh();
$workflow->approve($cms, $announcement, 'Approved', $fingerprints->for($announcement));
$ensure->handle($cms, 'announcement', null, 'Seasonal announcement');
$profile = $ensure->handle($cms, 'site_profile');
$payload = $profile->currentDraftRevision->payload;
$payload['brand']['description'] = 'Contemporary tailoring from Dar es Salaam';
$payload['contact']['email'] = 'studio@example.test';
$payload['contact']['whatsapp'] = '+255700000000';
$payload['social_links'][] = ['platform' => 'instagram', 'url' => 'https://instagram.com/williamtaylor', 'label' => 'William Taylor on Instagram'];
$payload['footer']['description'] = 'Tailoring with purpose.';
$save->handle($cms, $profile, $profile->current_draft_revision_id, $payload, 'Evidence profile');
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
$sessions = User::query()->where('email', 'like', 'be4g1.%')->get()->mapWithKeys(fn (User $user): array => [$user->email => $sessionCookie($user)])->all();
echo json_encode(['primary' => $primary->id, 'footer' => $footer->id, 'announcement' => $announcement->id, 'profile' => $profile->id, 'password_valid' => Hash::check($password, $cms->password), 'sessions' => $sessions], JSON_THROW_ON_ERROR);
