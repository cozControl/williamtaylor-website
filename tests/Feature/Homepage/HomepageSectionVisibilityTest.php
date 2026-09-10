<?php

namespace Tests\Feature\Homepage;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Homepage\Models\HomepageClientStory;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageSectionRegistry;
use App\Domain\Homepage\Support\HomepageSectionVisibility;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HomepageSectionVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::CMS_MANAGER));
        HomepageHero::create(['id' => HomepageHero::SINGLETON_ID, ...HomepageHero::defaults(), 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
    }

    public function test_defaults_registry_workspace_editors_and_single_settings_query(): void
    {
        $registry = HomepageSectionRegistry::all();
        $this->assertCount(10, $registry);
        $this->assertSame(range(1, 10), array_column($registry, 'position'));
        $this->assertArrayNotHasKey('follow-the-journey', $registry);
        DB::enableQueryLog();
        $page = $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "homepage_section_settings"'));
        $this->assertCount(1, $queries);
        DB::disableQueryLog();
        $this->assertSame(10, substr_count($page->getContent(), 'data-section-visibility="visible"'));
        $this->assertSame(10, substr_count($page->getContent(), 'role="switch" aria-checked="true"'));
        foreach ($registry as $key => $section) {
            $this->assertTrue($section['default_visible']);
            $page->assertSee(route('admin.homepage.visibility.update', $key), false);
            $this->get(route($section['edit_route']))->assertOk()->assertSee('data-editor-section-visibility="'.$key.'"', false)->assertSeeText('Manage visibility on Homepage');
        }
        $this->assertDatabaseCount('homepage_section_settings', 0);
    }

    public function test_visibility_authorization_validation_audit_and_configuration_preservation(): void
    {
        $url = route('admin.homepage.visibility.update', 'explore-collections');
        $this->put($url, ['is_visible' => '0'])->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ordinary)->put($url, ['is_visible' => '0'])->assertForbidden();
        $this->actingAs($this->manager)->put(route('admin.homepage.visibility.update', 'invented-section'), ['is_visible' => '0'])->assertNotFound();
        $this->put($url, ['is_visible' => 'invalid'])->assertSessionHasErrors('is_visible');
        $this->put($url, [])->assertSessionHasErrors('is_visible');
        $before = HomepageHero::findOrFail(HomepageHero::SINGLETON_ID)->getAttributes();
        $this->put($url, ['is_visible' => '0', 'explore_collections_managed' => '1', 'explore_collections_heading' => 'Must not change'])->assertRedirect(route('admin.homepage.edit'))->assertSessionHas('status', 'Explore the Collection is now hidden. Configuration preserved.');
        $this->assertSame($before, HomepageHero::findOrFail(HomepageHero::SINGLETON_ID)->getAttributes());
        $this->assertDatabaseHas('homepage_section_settings', ['section_key' => 'explore-collections', 'is_visible' => false]);
        $audit = AuditRecord::where('action', 'homepage.section_visibility.updated')->sole();
        $this->assertSame($this->manager->id, $audit->actor_user_id);
        $this->assertSame(['section' => 'explore-collections', 'is_visible' => true], $audit->before_summary);
        $this->assertSame(['section' => 'explore-collections', 'is_visible' => false], $audit->after_summary);
        $this->get(route('admin.homepage.edit'))->assertSee('role="switch" aria-checked="false"', false)->assertSeeText('Configuration preserved');
        $this->put($url, ['is_visible' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($before, HomepageHero::findOrFail(HomepageHero::SINGLETON_ID)->getAttributes());
        $this->assertDatabaseCount('homepage_section_settings', 1);
        $this->assertTrue(app(HomepageSectionVisibility::class)->isVisible('explore-collections'));
        $this->expectException(AuthorizationException::class);
        app(HomepageSectionVisibility::class)->setVisible($ordinary, 'hero', false);
    }

    public function test_visibility_precedes_both_content_modes_and_show_restores_managed_data(): void
    {
        $visibility = app(HomepageSectionVisibility::class);
        $this->get(route('home'))->assertOk()->assertSeeText('James M.');
        $visibility->setVisible($this->manager, 'client-stories', false);
        $this->get(route('home'))->assertOk()->assertDontSeeText('James M.')->assertDontSee('<section data-homepage-client-stories', false);
        $homepage = HomepageHero::findOrFail(HomepageHero::SINGLETON_ID);
        $homepage->forceFill(['client_stories_managed' => true, 'client_stories_heading' => 'Visibility fixture stories'])->save();
        $story = HomepageClientStory::create(['homepage_hero_id' => $homepage->id, 'position' => 1, 'is_visible' => true, 'display_name' => 'Fictional Visibility Client', 'location' => 'Test Location', 'quote' => 'Preserve this configured test quote.']);
        $id = (string) Str::ulid();
        $image = MediaAsset::create(['id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'visibility/'.$id, 'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg', 'original_filename' => 'fixture.jpg', 'internal_title' => 'Test image', 'default_alt_text' => 'Test image description', 'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false, 'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => now()]);
        $usage = MediaUsage::create(['media_asset_id' => $image->id, 'owner_type' => HomepageClientStory::class, 'owner_identifier' => $story->id, 'field_role' => 'portrait', 'decorative_override' => false, 'sort_order' => 0]);
        $story->refresh();
        $usage->refresh();
        $this->get(route('home'))->assertDontSeeText('Fictional Visibility Client')->assertDontSeeText('Visibility fixture stories')->assertDontSee('<template id="homepage-client-stories-projection">', false);
        $visibility->setVisible($this->manager, 'client-stories', true);
        $this->get(route('home'))->assertSeeText('Fictional Visibility Client')->assertSeeText('Preserve this configured test quote.')->assertSee('<template id="homepage-client-stories-projection">', false)->assertDontSeeText('James M.');
        $this->assertTrue($homepage->fresh()->client_stories_managed);
        $this->assertSame($story->getAttributes(), $story->fresh()->getAttributes());
        $this->assertSame($usage->getAttributes(), $usage->fresh()->getAttributes());
    }

    public function test_all_registry_boundaries_are_omitted_and_runtime_guards_cover_every_key(): void
    {
        $visibility = app(HomepageSectionVisibility::class);
        foreach (array_keys(HomepageSectionRegistry::all()) as $key) {
            $visibility->setVisible($this->manager, $key, false);
        }
        $response = $this->get(route('home'))->assertOk();
        $html = $response->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        foreach (HomepageSectionRegistry::all() as $key => $definition) {
            $this->assertSame(0, $xpath->query('//main//*[@'.$definition['marker'].']')->length, $key);
            $this->assertStringContainsString("Object.hasOwn(hiddenSections, '".$key."')", $html);
            $this->assertStringNotContainsString('<template id="homepage-'.$key.'-projection">', $html);
        }
        $this->assertSame(0, $xpath->query('//main//h1')->length);
        $this->assertSame(0, $xpath->query('//main//section[contains(@class,"relative bg-wt-oxblood overflow-hidden")]')->length);
        $this->assertStringNotContainsString('James M.', $html);
        $this->assertStringNotContainsString('Savanna Tote Bag', $html);
        $this->assertStringNotContainsString('Shop the Edit', $html);
        $payload = json_decode($xpath->query('//script[@id="homepage-hidden-sections"]')->item(0)->textContent, true);
        $this->assertSame(array_keys(HomepageSectionRegistry::all()), array_keys($payload));
        $this->assertStringContainsString('synchronizeVisibility(); synchronizeHero();', $html);
        $this->assertStringContainsString('node.remove();', $html);
        $this->assertStringNotContainsString("const hero = root.querySelector('main section')", $html);
        $response->assertSeeText('Follow the Journey');
    }

    public function test_shared_summer_delivery_container_respects_independent_visibility(): void
    {
        $visibility = app(HomepageSectionVisibility::class);
        $visibility->setVisible($this->manager, 'summer-edit', false);
        $this->get(route('home'))->assertOk()->assertDontSeeText('Shop the Edit')->assertSeeText('Shop with Confidence');
        $visibility->setVisible($this->manager, 'summer-edit', true);
        $visibility->setVisible($this->manager, 'delivery', false);
        $this->get(route('home'))->assertOk()->assertSeeText('Shop the Edit')->assertDontSeeText('Shop with Confidence');
    }
}
