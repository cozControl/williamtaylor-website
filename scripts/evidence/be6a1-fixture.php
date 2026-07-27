<?php

use App\Domain\Content\Actions\CreatePageDraft;
use App\Domain\Content\Actions\CreatePagePreviewUrl;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
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
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

EvidenceDatabaseGuard::assertDisposable((string) config('database.connections.sqlite.database'));
app(ProvisionRegisteredAccess::class)->handle();

$createUser = static function (string $email, bool $verified = true): User {
    $user = User::factory()->create([
        'name' => Str::headline(Str::before($email, '@')),
        'email' => $email,
        'email_verified_at' => $verified ? now() : null,
    ]);

    return $user;
};

$unverified = $createUser('be6a1.unverified@example.test', false);
$ordinary = $createUser('be6a1.ordinary@example.test');
$adminOnly = $createUser('be6a1.admin-only@example.test');
$adminOnly->givePermissionTo(PermissionRegistry::ADMIN_ACCESS);
$cms = $createUser('be6a1.cms@example.test');
app(ControlledRoleMutation::class)->run(fn () => $cms->assignRole(RoleRegistry::CMS_MANAGER));
$super = $createUser('be6a1.super@example.test');
app(ControlledRoleMutation::class)->run(fn () => $super->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));

$page = app(CreatePageDraft::class)->handle(
    $cms,
    'standard',
    'BE-6A.1 Browser Preview',
    'be6a1-browser-preview',
    'en',
    'standard_page',
);
$otherPage = app(CreatePageDraft::class)->handle(
    $cms,
    'standard',
    'BE-6A.1 Other Preview',
    'be6a1-other-preview',
    'en',
    'standard_page',
);

$ensure = app(EnsureSiteContent::class);
$save = app(SaveSiteContentDraft::class);
$workflow = app(SiteContentWorkflow::class);
$fingerprints = app(SiteContentFingerprint::class);
$footer = $ensure->handle($cms, 'footer_navigation');
$footerPayload = $footer->currentDraftRevision->payload;
$footerPayload['groups'][0]['label'] = 'Company';
$footerPayload['groups'][0]['links'] = [[
    'key' => 'collections',
    'label' => 'Collections',
    'link' => ['type' => 'internal_path', 'value' => '/collections'],
    'new_tab' => false,
]];
$save->handle($cms, $footer, $footer->current_draft_revision_id, $footerPayload, 'BE-6A.1 projected footer');
$footer = $footer->fresh();
$workflow->submit($cms, $footer, 'BE-6A.1 browser evidence', $fingerprints->for($footer));
$workflow->approve($cms, $footer->fresh(), 'Approved for isolated evidence', $fingerprints->for($footer->fresh()));
$workflow->publish($cms, $footer->fresh(), $fingerprints->for($footer->fresh()));

$sessionCookie = static function (User $user): array {
    app('session')->forgetDrivers();
    $store = app('session')->driver();
    $store->setId(Str::random(40));
    $store->start();
    $store->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
    $store->save();
    $name = (string) config('session.cookie');
    $prefixed = CookieValuePrefix::create($name, app('encrypter')->getKey()).$store->getId();

    return [
        'name' => $name,
        'value' => app('encrypter')->encrypt($prefixed, false),
        'path' => '/',
        'httpOnly' => true,
        'sameSite' => 'Lax',
    ];
};

$validPreview = app(CreatePagePreviewUrl::class)->handle($cms, $page, $page->currentDraftRevision);
$expiredPreview = URL::temporarySignedRoute(
    'preview.pages.show',
    now()->subMinute(),
    ['page' => $page, 'revision' => $page->currentDraftRevision],
);
$otherResourcePreview = URL::temporarySignedRoute(
    'preview.pages.show',
    now()->addMinutes(15),
    ['page' => $otherPage, 'revision' => $page->currentDraftRevision],
);
$otherRevisionPreview = URL::temporarySignedRoute(
    'preview.pages.show',
    now()->addMinutes(15),
    ['page' => $page, 'revision' => $otherPage->currentDraftRevision],
);

$users = compact('unverified', 'ordinary', 'adminOnly', 'cms', 'super');
$sessions = collect($users)->mapWithKeys(
    fn (User $user, string $key): array => [$key => $sessionCookie($user)],
)->all();

echo json_encode([
    'database' => 'disposable-sqlite',
    'actors' => collect($users)->map(fn (User $user): array => [
        'id' => $user->getKey(),
        'verified' => $user->hasVerifiedEmail(),
        'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
    ])->all(),
    'sessions' => $sessions,
    'preview' => [
        'valid' => $validPreview,
        'expired' => $expiredPreview,
        'other_resource' => $otherResourcePreview,
        'other_revision' => $otherRevisionPreview,
        'unsigned_path' => route('preview.pages.show', [$page, $page->currentDraftRevision], false),
    ],
    'resources' => [
        'page' => $page->getKey(),
        'revision' => $page->current_draft_revision_id,
        'footer' => $footer->getKey(),
    ],
], JSON_THROW_ON_ERROR);
