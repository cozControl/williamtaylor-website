<?php

use App\Domain\Content\Actions\CreatePageDraft;
use App\Domain\Content\Actions\SavePageDraftRevision;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$password = getenv('BE4E_EVIDENCE_PASSWORD');
if (! is_string($password) || strlen($password) < 12) {
    throw new RuntimeException('BE4E_EVIDENCE_PASSWORD must be at least 12 characters.');
}

app(ProvisionRegisteredAccess::class)->handle();
$cms = User::query()->updateOrCreate(
    ['email' => 'be4e.cms@example.test'],
    ['name' => 'BE-4E CMS Manager', 'password' => Hash::make($password), 'email_verified_at' => now()],
);
$cms->forceFill(['email_verified_at' => now()])->save();
app(ControlledRoleMutation::class)->run(fn () => $cms->syncRoles([RoleRegistry::CMS_MANAGER]));

$ordinary = User::query()->updateOrCreate(
    ['email' => 'be4e.user@example.test'],
    ['name' => 'BE-4E Ordinary User', 'password' => Hash::make($password), 'email_verified_at' => now()],
);
$ordinary->forceFill(['email_verified_at' => now()])->save();

$media = MediaAsset::query()->create([
    'provider_asset_id' => 'be4e-'.Str::ulid(),
    'provider_public_id' => 'testing/be4e/'.Str::ulid(),
    'resource_type' => MediaResourceType::Image,
    'format' => 'jpg',
    'mime_type' => 'image/jpeg',
    'original_filename' => 'editorial.jpg',
    'internal_title' => 'BE-4E editorial media',
    'default_alt_text' => 'Tailored jacket displayed in the studio',
    'width' => 1200,
    'height' => 1500,
    'bytes' => 1000,
    'accessibility_classification' => AccessibilityClassification::Informative,
    'state' => MediaAssetState::Ready,
    'provider_metadata' => [],
    'uploaded_by' => $cms->id,
    'confirmed_at' => now(),
]);

$page = app(CreatePageDraft::class)->handle(
    $cms,
    'landing',
    'BE-4E Editorial Landing',
    'be-4e-editorial-landing',
    'en',
    'editorial_landing',
);

$sections = [
    [
        'key' => (string) Str::ulid(),
        'type' => 'hero',
        'schema_version' => 1,
        'data' => [
            'eyebrow' => 'William Taylor',
            'heading' => 'Contemporary tailoring',
            'copy' => 'A controlled draft preview using immutable content.',
            'alignment' => 'left',
            'variant' => 'light',
            'desktop_media' => ['asset_id' => $media->id, 'alt_override' => '', 'decorative' => false],
        ],
    ],
    [
        'key' => (string) Str::ulid(),
        'type' => 'rich_text',
        'schema_version' => 1,
        'data' => [
            'document' => [
                'type' => 'doc',
                'content' => [
                    ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'A considered wardrobe']]],
                    ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Restricted rich text is validated and sanitized on the server.']]],
                ],
            ],
        ],
    ],
    [
        'key' => (string) Str::ulid(),
        'type' => 'cta',
        'schema_version' => 1,
        'data' => [
            'heading' => 'Continue the story',
            'copy' => 'This call to action is typed and code-rendered.',
            'primary_cta' => ['kind' => 'internal_path', 'label' => 'Return home', 'target' => '/'],
            'variant' => 'light',
        ],
    ],
];

app(SavePageDraftRevision::class)->handle(
    $cms,
    $page,
    $page->current_draft_revision_id,
    $page->title,
    $page->slug,
    $page->template_key,
    $sections,
    'Controlled browser evidence fixture',
);

echo json_encode(['page_id' => $page->id, 'media_id' => $media->id], JSON_THROW_ON_ERROR);
