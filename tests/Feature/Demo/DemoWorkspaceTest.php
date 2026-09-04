<?php

namespace Tests\Feature\Demo;

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Actions\SaveSiteContentDraft;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Fakes\FakeMediaProvider;
use Tests\TestCase;

final class DemoWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private User $cms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(MediaProvider::class, new FakeMediaProvider);
        app(ProvisionRegisteredAccess::class)->handle();
        $this->cms = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $this->cms->assignRole(RoleRegistry::CMS_MANAGER));
    }

    public function test_dashboard_uses_production_language_and_links_to_existing_workspaces(): void
    {
        config()->set('demo.enabled', true);
        config()->set('demo.allowed_environments', ['testing']);
        config()->set('publication_rollout.global_enabled', true);

        $this->get('/')->assertOk()->assertDontSee('Demo Environment');
        $this->actingAs($this->cms)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Website overview')
            ->assertDontSee('Demo Environment')
            ->assertDontSee('Client Demo')
            ->assertSee(route('admin.settings.index'), false)
            ->assertSee(route('admin.content.navigation.index'), false)
            ->assertSee(route('admin.content.announcements.index'), false)
            ->assertSee(route('admin.media.index'), false);
    }

    public function test_banner_is_hidden_when_demo_mode_is_inactive(): void
    {
        config()->set('demo.enabled', false);

        $this->actingAs($this->cms)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Content changes affect the client testing website only.');
    }

    public function test_admin_destinations_remain_permission_protected(): void
    {
        $ordinary = User::factory()->create();

        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->actingAs($ordinary)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($ordinary)->get(route('admin.settings.index'))->assertForbidden();
        $this->actingAs($this->cms)->get(route('admin.settings.index'))->assertOk();
    }

    public function test_site_profile_selector_shows_only_confirmed_ready_images_with_safe_alt_text(): void
    {
        $visible = $this->asset('ready-image', 'image', MediaAssetState::Ready, 'Meaningful portrait');
        $this->asset('missing-alt', 'image', MediaAssetState::Ready, null);
        $this->asset('video', 'video', MediaAssetState::Ready, 'Video');
        $this->asset('processing', 'image', MediaAssetState::Processing, 'Processing');

        $this->actingAs($this->cms)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee($visible->original_filename)
            ->assertSee('Alt text added')
            ->assertDontSee('missing-alt.jpg')
            ->assertDontSee('video.jpg')
            ->assertDontSee('processing.jpg');
    }

    public function test_site_settings_renders_distinct_controls_picker_and_state_aware_workflow(): void
    {
        $profile = app(EnsureSiteContent::class)->handle($this->cms, SiteContentTypeRegistry::SITE_PROFILE);

        $this->actingAs($this->cms)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Public brand name')
            ->assertSee('placeholder="Search filename or alt text"', false)
            ->assertSee('Change description')
            ->assertSee('Submit for review')
            ->assertDontSee('Approve</button>', false)
            ->assertDontSee('Publish now')
            ->assertDontSee('Schedule approved version')
            ->assertSee('cms-site-content input:not([type=checkbox])', false)
            ->assertSee('cms-repeat-row', false)
            ->assertSee('Version history')
            ->assertSee('Initial Site settings draft')
            ->assertSee('Brand information changed')
            ->assertDontSee('<strong>brand</strong>', false);

        $this->assertSame('site_profile', $profile->type);
    }

    public function test_forged_non_image_media_selection_is_rejected_without_revision_or_usage(): void
    {
        $profile = app(EnsureSiteContent::class)->handle($this->cms, SiteContentTypeRegistry::SITE_PROFILE);
        $video = $this->asset('forged-video', 'video', MediaAssetState::Ready, 'Video');
        $payload = $profile->currentDraftRevision->payload;
        $payload['footer']['footer_image_id'] = $video->id;
        $before = $profile->revisions()->count();

        try {
            app(SaveSiteContentDraft::class)->handle($this->cms, $profile, $profile->current_draft_revision_id, $payload, 'Forged media');
            $this->fail('A video was accepted for an image-only Site Content field.');
        } catch (InvalidArgumentException) {
            $this->assertSame($before, $profile->revisions()->count());
            $this->assertDatabaseCount('media_usages', 0);
            $this->assertDatabaseMissing('audit_records', ['action' => 'site-content.draft-saved', 'subject_id' => $profile->id]);
        }
    }

    private function asset(string $key, string $type, MediaAssetState $state, ?string $alt): MediaAsset
    {
        return MediaAsset::query()->create([
            'provider' => 'testing',
            'provider_asset_id' => $key,
            'provider_public_id' => "testing/{$key}",
            'resource_type' => $type,
            'delivery_type' => 'upload',
            'format' => $type === 'image' ? 'jpg' : 'mp4',
            'mime_type' => $type === 'image' ? 'image/jpeg' : 'video/mp4',
            'original_filename' => "{$key}.jpg",
            'internal_title' => $key,
            'default_alt_text' => $alt,
            'width' => 1200,
            'height' => 800,
            'bytes' => 1000,
            'accessibility_classification' => 'informative',
            'is_decorative' => false,
            'state' => $state,
            'confirmed_at' => now(),
            'uploaded_by' => $this->cms->id,
        ]);
    }
}
