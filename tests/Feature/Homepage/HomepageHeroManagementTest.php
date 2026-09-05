<?php

namespace Tests\Feature\Homepage;

use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HomepageHeroManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::CMS_MANAGER));
    }

    public function test_homepage_navigation_and_route_are_authorized_server_side(): void
    {
        $this->get(route('admin.homepage.edit'))->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $ordinary->givePermissionTo('admin.access');
        $this->actingAs($ordinary)->get(route('admin.homepage.edit'))->assertForbidden();
        $this->actingAs($ordinary)->put(route('admin.homepage.update'), [])->assertForbidden();

        $this->actingAs($this->manager)->get(route('admin.dashboard'))->assertOk()
            ->assertSeeTextInOrder(['Homepage', 'Site settings', 'Pages', 'Navigation', 'Announcements', 'Media library']);
    }

    public function test_editor_bootstraps_exact_existing_values_and_uses_reusable_media_picker(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk()
            ->assertSee('data-admin-ui-revision="ecom-home-1"', false)
            ->assertSee('value="Tanzania · 2026 Collection"', false)
            ->assertSee('value="William Taylor"', false)
            ->assertSee('value="Contemporary Menswear"', false)
            ->assertSeeText('Shop New Arrivals')
            ->assertSeeText('Explore Collections')
            ->assertSee('data-media-picker="homepage-hero-media"', false)
            ->assertSeeText('Choose media')
            ->assertSeeText('View homepage')
            ->assertSeeTextInOrder(['Back', 'Save changes']);

        $this->assertDatabaseHas('homepage_heroes', ['id' => HomepageHero::SINGLETON_ID]);
    }

    public function test_manager_directly_saves_typed_hero_and_public_projection(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $hero = HomepageHero::query()->sole();
        $image = $this->image();
        $response = $this->actingAs($this->manager)->put(route('admin.homepage.update'), [
            'lock_version' => $hero->lock_version,
            'eyebrow' => 'Tanzania · Autumn 2026',
            'title' => 'William Taylor Atelier',
            'subtitle' => 'Made with intention',
            'primary_cta_label' => 'Discover New Arrivals',
            'primary_cta_destination' => 'new_arrivals',
            'secondary_cta_label' => 'Browse Collections',
            'secondary_cta_destination' => 'collections',
            'scroll_indicator_enabled' => '1',
            'background_media_id' => $image->id,
        ]);

        $response->assertRedirect(route('admin.homepage.edit'))->assertSessionHas('status', 'Homepage Hero updated successfully.');
        $this->assertDatabaseHas('media_usages', ['owner_type' => HomepageHero::class, 'owner_identifier' => $hero->id, 'media_asset_id' => $image->id, 'field_role' => HomepageHero::MEDIA_ROLE]);
        $this->assertDatabaseHas('audit_records', ['action' => 'homepage.hero.updated', 'resource_identifier' => $hero->id]);
        $this->get(route('home'))->assertOk()
            ->assertSeeText('Tanzania · Autumn 2026')
            ->assertSeeText('William Taylor Atelier')
            ->assertSeeText('Made with intention')
            ->assertSeeText('Discover New Arrivals')
            ->assertSeeText('Browse Collections')
            ->assertSee('homepage-hero-data', false)
            ->assertSee('homepage/'.$image->id, false)
            ->assertSee('data-homepage-hero', false);
    }

    public function test_validation_preserves_input_and_stale_write_is_rejected(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $hero = HomepageHero::query()->sole();
        $this->actingAs($this->manager)->from(route('admin.homepage.edit'))->put(route('admin.homepage.update'), [
            'lock_version' => $hero->lock_version,
            'eyebrow' => '<script>',
            'title' => '',
            'subtitle' => 'Retained subtitle',
            'primary_cta_label' => 'Shop',
            'primary_cta_destination' => 'unbounded',
            'secondary_cta_label' => 'Collections',
            'secondary_cta_destination' => 'collections',
        ])->assertRedirect(route('admin.homepage.edit'))->assertSessionHasErrors(['eyebrow', 'title', 'primary_cta_destination'])->assertSessionHasInput('subtitle', 'Retained subtitle');

        $payload = [...HomepageHero::defaults(), 'lock_version' => 0, 'background_media_id' => null];
        $this->actingAs($this->manager)->put(route('admin.homepage.update'), $payload)
            ->assertSessionHasErrors('lock_version');
    }

    public function test_public_homepage_uses_exact_static_fallback_without_record(): void
    {
        $this->assertDatabaseCount('homepage_heroes', 0);
        $this->get(route('home'))->assertOk()
            ->assertSeeText('Tanzania · 2026 Collection')
            ->assertSeeText('William Taylor')
            ->assertSeeText('Contemporary Menswear')
            ->assertSeeText('Shop New Arrivals')
            ->assertSeeText('Explore Collections')
            ->assertSee('/website/images/306170464_Screenshot2026-07-10at215946.png', false);
    }

    private function image(): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'homepage/'.$id,
            'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg',
            'original_filename' => 'homepage-hero.jpg', 'internal_title' => 'Homepage Hero', 'default_alt_text' => 'Homepage Hero',
            'accessibility_classification' => AccessibilityClassification::Decorative, 'is_decorative' => true,
            'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => now(),
        ]);
    }
}
