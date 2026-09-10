<?php

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Actions\SaveSiteContentDraft;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Services\SiteContentWorkflow;
use App\Domain\SiteContent\Support\EvidenceDatabaseGuard;
use App\Domain\SiteContent\Support\SiteContentFingerprint;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
EvidenceDatabaseGuard::assertDisposable((string) config('database.connections.sqlite.database'));
$statePath = dirname(__DIR__, 2).'/storage/app/evidence/be-4h-a/fixture.json';
$action = $argv[1] ?? 'setup';

if ($action === 'setup') {
    app(ProvisionRegisteredAccess::class)->handle();
    $actor = User::factory()->create(['email' => 'be4ha.cms@example.test']);
    app(ControlledRoleMutation::class)->run(fn () => $actor->assignRole(RoleRegistry::CMS_MANAGER));
    $ensure = app(EnsureSiteContent::class);
    $save = app(SaveSiteContentDraft::class);
    $workflow = app(SiteContentWorkflow::class);
    $fingerprints = app(SiteContentFingerprint::class);
    $publish = function (SiteContent $resource) use ($actor, $workflow, $fingerprints): SiteContent {
        $workflow->submit($actor, $resource, 'BE-4H-A.1 evidence', $fingerprints->for($resource->fresh()));
        $workflow->approve($actor, $resource, 'Approved for evidence', $fingerprints->for($resource->fresh()));
        $workflow->publish($actor, $resource, $fingerprints->for($resource->fresh()));

        return $resource->fresh();
    };

    $navigation = $ensure->handle($actor, 'primary_navigation');
    $save->handle($actor, $navigation, $navigation->current_draft_revision_id, ['items' => [
        ['key' => 'collections', 'label' => 'Governed Collections', 'link' => ['type' => 'internal_path', 'value' => '/collections'], 'new_tab' => false, 'visibility' => 'all', 'children' => [
            ['key' => 'limited', 'label' => 'Limited Edition', 'link' => ['type' => 'internal_path', 'value' => '/limited-edition'], 'new_tab' => false, 'visibility' => 'all'],
        ]],
        ['key' => 'shop', 'label' => 'Shop All', 'link' => ['type' => 'internal_path', 'value' => '/shop'], 'new_tab' => false, 'visibility' => 'all', 'children' => []],
    ]], 'Evidence navigation');
    $navigation = $publish($navigation->fresh());

    $footer = $ensure->handle($actor, 'footer_navigation');
    $payload = $footer->currentDraftRevision->payload;
    $payload['groups'][0]['label'] = 'Company';
    $payload['groups'][0]['links'] = [['key' => 'collections', 'label' => 'Collections', 'link' => ['type' => 'internal_path', 'value' => '/collections'], 'new_tab' => false]];
    $payload['groups'][1]['label'] = 'Customer Care';
    $payload['groups'][1]['links'] = [['key' => 'wishlist', 'label' => 'Wishlist', 'link' => ['type' => 'internal_path', 'value' => '/wishlist'], 'new_tab' => false]];
    $payload['groups'][2]['label'] = 'Legal';
    $payload['groups'][2]['links'] = [['key' => 'home', 'label' => 'Home', 'link' => ['type' => 'internal_path', 'value' => '/'], 'new_tab' => false]];
    $save->handle($actor, $footer, $footer->current_draft_revision_id, $payload, 'Evidence footer');
    $footer = $publish($footer->fresh());

    $announcement = $ensure->handle($actor, 'announcement', null, 'Public evidence announcement');
    $payload = $announcement->currentDraftRevision->payload;
    $payload['message'] = 'BE-4H-A.1 Governed Announcement';
    $payload['dismissible'] = true;
    $payload['accessibility_label'] = 'Dismiss governed announcement';
    $save->handle($actor, $announcement, $announcement->current_draft_revision_id, $payload, 'Evidence announcement');
    $announcement = $publish($announcement->fresh());

    $profile = $ensure->handle($actor, 'site_profile');
    $payload = $profile->currentDraftRevision->payload;
    $payload['brand']['name'] = 'William Taylor';
    $payload['contact']['email'] = 'studio@example.test';
    $payload['contact']['telephone'] = '+255 700 000 000';
    $payload['contact']['whatsapp'] = '+255700000000';
    $payload['contact']['address'] = 'Dar es Salaam, Tanzania';
    $payload['social_links'] = [['platform' => 'instagram', 'url' => 'https://instagram.com/williamtaylorbrand', 'label' => 'William Taylor on Instagram']];
    $payload['footer']['description'] = 'Governed tailoring from Tanzania.';
    $payload['footer']['copyright'] = '2026 William Taylor. All Rights Reserved.';
    $payload['footer']['newsletter_heading'] = 'Join the Governed Ledger';
    $payload['footer']['newsletter_copy'] = 'Receive approved atelier updates.';
    $save->handle($actor, $profile, $profile->current_draft_revision_id, $payload, 'Evidence profile');
    $profile = $publish($profile->fresh());

    $state = ['actor' => $actor->id, 'navigation' => $navigation->id, 'footer' => $footer->id, 'announcement' => $announcement->id, 'profile' => $profile->id, 'public' => [
        'navigation' => $navigation->publicationState->current_public_revision_id,
        'footer' => $footer->publicationState->current_public_revision_id,
        'announcement' => $announcement->publicationState->current_public_revision_id,
        'profile' => $profile->publicationState->current_public_revision_id,
    ]];
    if (! is_dir(dirname($statePath))) {
        mkdir(dirname($statePath), 0777, true);
    }
    file_put_contents($statePath, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
} else {
    $state = json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
    foreach ($state['public'] as $key => $revisionId) {
        DB::table('site_content_publication_states')->where('site_content_id', $state[$key])->update(['current_public_revision_id' => $revisionId]);
    }
    if ($action === 'no-announcement') {
        DB::table('site_content_publication_states')->where('site_content_id', $state['announcement'])->update(['current_public_revision_id' => null]);
    }
    if (in_array($action, ['invalid-navigation', 'invalid-media'], true)) {
        $resourceKey = $action === 'invalid-navigation' ? 'navigation' : 'profile';
        $resource = SiteContent::query()->findOrFail($state[$resourceKey]);
        $source = ContentRevision::query()->findOrFail($state['public'][$resourceKey]);
        $payload = $source->payload;
        if ($action === 'invalid-navigation') {
            $payload['items'][0]['link']['value'] = 'javascript:alert(1)';
        } else {
            $payload['brand']['header_logo_id'] = (string) Str::ulid();
        }
        $revision = ContentRevision::query()->create([
            'resource_type' => SiteContent::class,
            'resource_id' => $resource->id,
            'revision_number' => ContentRevision::query()->where('resource_type', SiteContent::class)->where('resource_id', $resource->id)->max('revision_number') + 1,
            'schema_version' => $source->schema_version,
            'payload' => $payload,
            'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'sanitizer_version' => $source->sanitizer_version,
            'change_summary' => 'Disposable invalid fallback evidence',
            'created_by' => $state['actor'],
            'created_at' => now('UTC'),
        ]);
        DB::table('site_content_publication_states')->where('site_content_id', $resource->id)->update(['current_public_revision_id' => $revision->id]);
    }
}
Cache::clear();
echo json_encode(['action' => $action, 'state' => $state], JSON_THROW_ON_ERROR);
