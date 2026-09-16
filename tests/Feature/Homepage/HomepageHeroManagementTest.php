<?php

namespace Tests\Feature\Homepage;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\CollectionRevision;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageHeroPresenter;
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

    public function test_homepage_navigation_and_routes_are_authorized_server_side(): void
    {
        $this->get(route('admin.homepage.edit'))->assertRedirect(route('login'));
        $this->get(route('admin.homepage.hero.edit'))->assertRedirect(route('login'));

        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $ordinary->givePermissionTo('admin.access');
        $this->actingAs($ordinary)->get(route('admin.homepage.edit'))->assertForbidden();
        $this->actingAs($ordinary)->get(route('admin.homepage.hero.edit'))->assertForbidden();
        $this->actingAs($ordinary)->put(route('admin.homepage.hero.update'), [])->assertForbidden();
        $this->actingAs($ordinary)->put(route('admin.homepage.update'), [])->assertForbidden();

        $this->actingAs($this->manager)->get(route('admin.dashboard'))->assertOk()
            ->assertSeeTextInOrder(['Homepage', 'Site settings', 'Pages', 'Navigation', 'Announcements', 'Media library']);
    }

    public function test_homepage_workspace_lists_ten_registered_sections_in_storefront_order(): void
    {
        $response = $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk()
            ->assertSee('data-admin-ui-revision="ecom-home-2e"', false)
            ->assertSeeText('Manage the content and merchandising sections shown on your storefront.')
            ->assertSeeText('View homepage')
            ->assertSee('href="'.route('home').'" target="_blank" rel="noopener">View homepage</a>', false)
            ->assertSeeTextInOrder([
                'Homepage Hero',
                'Manage Hero',
                'New Arrivals',
                'Manage New Arrivals',
                "William's Hot Sale",
                'Manage Hot Sale',
                'The Future of Style',
                'Manage The Future of Style',
                'Limited Edition',
                'Manage Limited Edition',
                'Explore the Collection',
                'Manage Explore the Collection',
            ])
            ->assertSee(route('admin.homepage.hero.edit'), false)
            ->assertSee(route('admin.homepage.new-arrivals.edit'), false)
            ->assertSee(route('admin.homepage.hot-sale.edit'), false)
            ->assertSee(route('admin.homepage.future-style.edit'), false)
            ->assertSee(route('admin.homepage.limited-edition.edit'), false)
            ->assertSee(route('admin.homepage.explore-collections.edit'), false)
            ->assertDontSee('name="eyebrow"', false)
            ->assertDontSee('data-media-picker="homepage-hero-media"', false)
            ->assertDontSeeText('canonical')
            ->assertDontSeeText('protected')
            ->assertDontSeeText('static fallback')
            ->assertDontSeeText('projection')
            ->assertDontSeeText('presenter');

        $this->assertSame(10, substr_count($response->getContent(), 'data-homepage-section='));
        $this->assertDatabaseHas('homepage_heroes', ['id' => HomepageHero::SINGLETON_ID]);
    }

    public function test_hero_editor_bootstraps_exact_existing_values_and_uses_reusable_media_picker(): void
    {
        $response = $this->actingAs($this->manager)->get(route('admin.homepage.hero.edit'))->assertOk();
        $hero = HomepageHero::query()->sole();

        $response
            ->assertSee('data-admin-ui-revision="ecom-home-2e"', false)
            ->assertSee('value="'.e($hero->eyebrow).'"', false)
            ->assertSee('value="William Taylor"', false)
            ->assertSee('value="Contemporary Menswear"', false)
            ->assertSeeText('Shop New Arrivals')
            ->assertSeeText('Explore Collections')
            ->assertSee('data-media-picker="homepage-hero-media"', false)
            ->assertSeeText('Choose media')
            ->assertSeeText('View homepage')
            ->assertSeeTextInOrder(['Homepage Hero', 'Hero image', 'Primary action', 'Secondary action'])
            ->assertSeeTextInOrder(['Back to Homepage', 'Save changes']);

        $this->assertMatchesRegularExpression('/<section class="admin-panel" data-homepage-hero-card>.*data-media-picker="homepage-hero-media".*<\/section>/s', $response->getContent());
    }

    public function test_manager_directly_saves_typed_hero_and_public_projection(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.hero.edit'))->assertOk();
        $hero = HomepageHero::query()->sole();
        $image = $this->image();
        $response = $this->actingAs($this->manager)->put(route('admin.homepage.hero.update'), [
            'lock_version' => $hero->lock_version,
            'eyebrow' => 'Tanzania Autumn 2026',
            'title' => 'William Taylor Atelier',
            'subtitle' => 'Made with intention',
            'primary_cta_label' => 'Discover New Arrivals',
            'primary_cta_destination' => 'new_arrivals',
            'secondary_cta_label' => 'Browse Collections',
            'secondary_cta_destination' => 'collections',
            'scroll_indicator_enabled' => '1',
            'background_media_id' => $image->id,
        ]);

        $response->assertRedirect(route('admin.homepage.hero.edit'))->assertSessionHas('status', 'Homepage Hero updated successfully.');
        $this->assertDatabaseHas('media_usages', ['owner_type' => HomepageHero::class, 'owner_identifier' => $hero->id, 'media_asset_id' => $image->id, 'field_role' => HomepageHero::MEDIA_ROLE]);
        $this->assertDatabaseHas('audit_records', ['action' => 'homepage.hero.updated', 'resource_identifier' => $hero->id]);
        $this->actingAs($this->manager)->get(route('admin.homepage.hero.edit'))->assertOk()
            ->assertSee($image->id, false)
            ->assertSeeText('Change media');
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk()
            ->assertSeeText('Background image configured.')
            ->assertSeeText('Configured');
        $this->get(route('home'))->assertOk()
            ->assertSeeText('Tanzania Autumn 2026')
            ->assertSeeText('William Taylor Atelier')
            ->assertSeeText('Made with intention')
            ->assertSeeText('Discover New Arrivals')
            ->assertSeeText('Browse Collections')
            ->assertSee('homepage-hero-data', false)
            ->assertSee('homepage/'.$image->id, false)
            ->assertSee('data-homepage-hero', false)
            ->assertSee('@media (max-width: 767px)', false)
            ->assertSee('height: 100svh', false)
            ->assertSee('data-homepage-hero-actions', false);
    }

    public function test_validation_preserves_input_and_stale_write_is_rejected(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.hero.edit'))->assertOk();
        $hero = HomepageHero::query()->sole();
        $this->actingAs($this->manager)->from(route('admin.homepage.hero.edit'))->put(route('admin.homepage.hero.update'), [
            'lock_version' => $hero->lock_version,
            'eyebrow' => '<script>',
            'title' => '',
            'subtitle' => 'Retained subtitle',
            'primary_cta_label' => 'Shop',
            'primary_cta_destination' => 'unbounded',
            'secondary_cta_label' => 'Collections',
            'secondary_cta_destination' => 'collections',
        ])->assertRedirect(route('admin.homepage.hero.edit'))
            ->assertSessionHasErrors(['eyebrow', 'title', 'primary_cta_destination'])
            ->assertSessionHasInput('subtitle', 'Retained subtitle');

        $payload = [...HomepageHero::defaults(), 'lock_version' => 0, 'background_media_id' => null];
        $this->actingAs($this->manager)->put(route('admin.homepage.hero.update'), $payload)
            ->assertSessionHasErrors('lock_version');
    }

    public function test_legacy_hero_update_route_remains_compatible(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.hero.edit'))->assertOk();
        $hero = HomepageHero::query()->sole();

        $this->actingAs($this->manager)->put(route('admin.homepage.update'), [
            ...HomepageHero::defaults(),
            'lock_version' => $hero->lock_version,
            'background_media_id' => null,
        ])->assertRedirect(route('admin.homepage.hero.edit'));
    }

    public function test_public_homepage_section_order_remains_unchanged(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $offset = 0;

        foreach ([
            '<section data-homepage-hero',
            'New Arrivals',
            "William's Hot Sale",
            'The Future of Style',
            'LIMITED EDITION',
            'Explore the Collection',
            'The Summer Edit',
        ] as $marker) {
            $position = strpos($html, $marker, $offset);
            $this->assertNotFalse($position, 'Missing or out-of-order Homepage marker: '.$marker);
            $offset = $position + strlen($marker);
        }
    }

    public function test_public_homepage_uses_exact_static_fallback_without_record(): void
    {
        $defaults = HomepageHero::defaults();
        $this->assertDatabaseCount('homepage_heroes', 0);
        $this->get(route('home'))->assertOk()
            ->assertSeeText($defaults['eyebrow'])
            ->assertSeeText($defaults['title'])
            ->assertSeeText($defaults['subtitle'])
            ->assertSeeText($defaults['primary_cta_label'])
            ->assertSeeText($defaults['secondary_cta_label'])
            ->assertSee('/website/images/306170464_Screenshot2026-07-10at215946.png', false);
    }

    public function test_both_hero_actions_can_select_collections_and_follow_current_urls(): void
    {
        $collections = [];
        foreach (['Tailoring', 'Accessories'] as $title) {
            $collection = Collection::query()->create(['collection_type' => 'manual', 'slug' => Str::slug($title), 'catalogue_status' => 'ready', 'created_by' => $this->manager->id]);
            $revision = CollectionRevision::query()->create(['collection_id' => $collection->id, 'revision_number' => 1, 'title' => $title, 'short_description' => $title, 'checksum' => hash('sha256', $title), 'created_by' => $this->manager->id, 'created_at' => now()]);
            $collection->update(['current_draft_revision_id' => $revision->id]);
            $collections[] = $collection;
        }
        [$primary, $secondary] = $collections;
        $response = $this->actingAs($this->manager)->get(route('admin.homepage.hero.edit'))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), 'value="collection:'.$primary->id.'"'));
        $this->assertSame(2, substr_count($response->getContent(), 'value="collection:'.$secondary->id.'"'));
        $hero = HomepageHero::query()->sole();
        $payload = [...HomepageHero::defaults(), 'lock_version' => $hero->lock_version, 'primary_cta_destination' => 'collection:'.$primary->id, 'secondary_cta_destination' => 'collection:'.$secondary->id];
        $this->put(route('admin.homepage.hero.update'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('admin.homepage.hero.edit'));
        $this->assertDatabaseHas('homepage_heroes', ['id' => $hero->id, 'primary_cta_destination' => 'collection:'.$primary->id, 'secondary_cta_destination' => 'collection:'.$secondary->id]);
        $primary->update(['slug' => 'new-tailoring']);
        $projection = app(HomepageHeroPresenter::class)->present();
        $this->assertSame(route('collections.show', 'new-tailoring'), $projection['primary_cta_url']);
        $this->assertSame(route('collections.show', $secondary->slug), $projection['secondary_cta_url']);
        $this->get(route('home'))->assertOk()->assertSee('href="'.$projection['primary_cta_url'].'"', false)->assertSee('href="'.$projection['secondary_cta_url'].'"', false);

        $primary->update(['archived_at' => now()]);
        $secondary->update(['catalogue_status' => 'draft']);
        $projection = app(HomepageHeroPresenter::class)->present();
        $this->assertSame(route('collections.index'), $projection['primary_cta_url']);
        $this->assertSame(route('collections.index'), $projection['secondary_cta_url']);
        $this->get(route('admin.homepage.hero.edit'))->assertOk()->assertSeeText('Choose an available Collection')->assertDontSee('value="collection:'.$primary->id.'"', false)->assertDontSee('value="collection:'.$secondary->id.'"', false);
        $payload['lock_version'] = $hero->fresh()->lock_version;
        $this->put(route('admin.homepage.hero.update'), $payload)->assertSessionHasErrors(['primary_cta_destination', 'secondary_cta_destination']);
        $payload['primary_cta_destination'] = 'collection:'.Str::ulid();
        $payload['secondary_cta_destination'] = 'https://example.com';
        $this->put(route('admin.homepage.hero.update'), $payload)->assertSessionHasErrors(['primary_cta_destination', 'secondary_cta_destination']);
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
