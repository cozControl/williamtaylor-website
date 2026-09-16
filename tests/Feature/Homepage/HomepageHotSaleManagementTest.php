<?php

namespace Tests\Feature\Homepage;

use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageHotSalePresenter;
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
use Tests\Support\CollectionCatalogueFixture;
use Tests\TestCase;

final class HomepageHotSaleManagementTest extends TestCase
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

    public function test_hot_sale_editor_is_authorized_and_loads_template_fields_and_lazy_media_pickers(): void
    {
        $this->get(route('admin.homepage.hot-sale.edit'))->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $ordinary->givePermissionTo('admin.access');
        $this->actingAs($ordinary)->get(route('admin.homepage.hot-sale.edit'))->assertForbidden();
        $this->actingAs($ordinary)->put(route('admin.homepage.hot-sale.update'), [])->assertForbidden();

        $this->actingAs($this->manager)->get(route('admin.homepage.hot-sale.edit'))->assertOk()
            ->assertSeeText("William's Hot Sale")->assertSee('value="The Atelier Edit"', false)
            ->assertSee('value="The Shopping Experience"', false)->assertSee('value="The Signature Bag"', false)
            ->assertSee('data-media-picker="hot-sale-1-media"', false)
            ->assertSee('data-media-picker="hot-sale-2-media"', false)
            ->assertSee('data-media-picker="hot-sale-3-media"', false)
            ->assertSeeTextInOrder(['Back to Homepage', 'Save changes']);
    }

    public function test_each_tile_can_save_a_collection_destination_and_follow_its_current_slug(): void
    {
        $fixture = new CollectionCatalogueFixture($this->manager);
        $collections = [$fixture->collection('formal-wear'), $fixture->collection('casual-wear'), $fixture->collection('handbags')];
        $response = $this->actingAs($this->manager)->get(route('admin.homepage.hot-sale.edit'))->assertOk();
        foreach ($collections as $collection) {
            $response->assertSee('value="collection:'.$collection->id.'"', false);
            $this->assertSame(3, substr_count($response->getContent(), 'value="collection:'.$collection->id.'"'));
        }
        $homepage = HomepageHero::query()->sole();
        $payload = $this->payload($homepage, [$this->image('atelier'), $this->video('experience'), $this->image('signature')]);
        foreach ($collections as $index => $collection) {
            $payload['hot_sale_tile_'.($index + 1).'_destination'] = 'collection:'.$collection->id;
        }
        $this->put(route('admin.homepage.hot-sale.update'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $homepage->refresh();
        foreach ($collections as $index => $collection) {
            $this->assertSame('collection:'.$collection->id, $homepage->{'hot_sale_tile_'.($index + 1).'_destination'});
        }
        $tiles = app(HomepageHotSalePresenter::class)->present()['tiles'];
        foreach ($collections as $index => $collection) {
            $this->assertSame(route('collections.show', $collection->slug), $tiles[$index]['url']);
        }
        $collections[0]->update(['slug' => 'formal-edit']);
        $this->assertSame(route('collections.show', 'formal-edit'), app(HomepageHotSalePresenter::class)->present()['tiles'][0]['url']);
        $this->get(route('home'))->assertOk()->assertSee(route('collections.show', 'formal-edit'), false);

        $collections[1]->update(['archived_at' => now()]);
        $collections[2]->update(['catalogue_status' => 'draft']);
        $tiles = app(HomepageHotSalePresenter::class)->present()['tiles'];
        $this->assertSame(route('collections.index'), $tiles[1]['url']);
        $this->assertSame(route('collections.index'), $tiles[2]['url']);
        $this->get(route('admin.homepage.hot-sale.edit'))->assertOk()->assertSeeText('Collection unavailable — choose a destination')
            ->assertDontSee('value="collection:'.$collections[1]->id.'"', false)
            ->assertDontSee('value="collection:'.$collections[2]->id.'"', false);
    }

    public function test_unavailable_and_arbitrary_collection_destinations_are_rejected_without_changing_tiles(): void
    {
        $fixture = new CollectionCatalogueFixture($this->manager);
        $draft = $fixture->collection('draft-collection');
        $draft->update(['catalogue_status' => 'draft']);
        $archived = $fixture->collection('archived-collection');
        $archived->update(['archived_at' => now()]);
        $this->actingAs($this->manager)->get(route('admin.homepage.hot-sale.edit'))->assertOk()
            ->assertDontSee('value="collection:'.$draft->id.'"', false)
            ->assertDontSee('value="collection:'.$archived->id.'"', false);
        $homepage = HomepageHero::query()->sole();
        $payload = $this->payload($homepage, [$this->image('atelier'), $this->video('experience'), $this->image('signature')]);
        foreach (['collection:'.$draft->id, 'collection:'.$archived->id, 'collection:'.Str::ulid(), 'https://example.com'] as $invalid) {
            $this->put(route('admin.homepage.hot-sale.update'), [...$payload, 'hot_sale_tile_1_destination' => $invalid])
                ->assertSessionHasErrors('hot_sale_tile_1_destination');
            $this->assertSame('shop_newest', $homepage->fresh()->hot_sale_tile_1_destination);
            $this->assertDatabaseCount('media_usages', 2); // Collection cover usages only.
        }
    }

    public function test_manager_saves_three_editorial_tiles_and_public_homepage_uses_exact_composition(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $images = [$this->image('atelier'), $this->video('experience'), $this->image('signature')];

        $this->actingAs($this->manager)->put(route('admin.homepage.hot-sale.update'), $this->payload($homepage, $images))
            ->assertRedirect(route('admin.homepage.hot-sale.edit'))
            ->assertSessionHas('status', "William's Hot Sale updated successfully.");

        $this->assertDatabaseHas('homepage_heroes', ['id' => $homepage->id, 'hot_sale_managed' => true, 'hot_sale_heading' => "William's Hot Sale"]);
        $this->assertDatabaseCount('media_usages', 3);
        $this->assertDatabaseHas('media_usages', ['media_asset_id' => $images[0]->id, 'alt_text_override' => null]);
        $this->assertDatabaseHas('audit_records', ['action' => 'homepage.hot-sale.updated', 'resource_identifier' => $homepage->id]);

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('data-homepage-hot-sale', false)
            ->assertSee('<template id="homepage-hot-sale-projection">', false)
            ->assertSee('synchronizeHotSale', false)
            ->assertSeeTextInOrder(['The Atelier Edit', 'The Shopping Experience', 'The Signature Bag'])
            ->assertSeeText('Discover')->assertSee(route('products.index', ['sort' => 'newest']), false)
            ->assertSee(route('collections.index'), false)->assertSee(route('products.index'), false)
            ->assertSee('wt-hot-sale-grid', false)
            ->assertSee('<video', false)->assertSee('autoplay muted loop playsinline', false)
            ->assertSee('group-hover:scale-108', false)->assertSee('group-hover:-translate-y-2', false);
        $this->assertSame(6, substr_count($response->getContent(), '<article data-hot-sale-tile='));
    }

    public function test_full_bleed_composition_restores_section_headings_and_preserves_stored_copy(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.hot-sale.edit'))->assertOk()
            ->assertSee('name="hot_sale_heading"', false)->assertSee('name="hot_sale_eyebrow"', false);
        $homepage = HomepageHero::query()->sole();
        $homepage->update(['hot_sale_heading' => 'Historical heading', 'hot_sale_eyebrow' => 'Historical eyebrow']);
        $images = [$this->image('atelier'), $this->video('experience'), $this->image('signature')];
        $payload = $this->payload($homepage->fresh(), $images);
        unset($payload['hot_sale_heading'], $payload['hot_sale_eyebrow']);
        $this->put(route('admin.homepage.hot-sale.update'), $payload)->assertSessionHasNoErrors();
        $this->assertSame('Historical heading', $homepage->fresh()->hot_sale_heading);
        $this->assertSame('Historical eyebrow', $homepage->fresh()->hot_sale_eyebrow);
        $html = $this->get(route('home'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $section = $xpath->query('//main//section[@data-homepage-hot-sale]')->item(0);
        $this->assertNotNull($section);
        $this->assertSame(2, $xpath->query('.//h2|.//*[contains(@class,"section-subtitle")]', $section)->length);
        $this->assertSame(3, $xpath->query('.//article[@data-hot-sale-tile]', $section)->length);
        $this->assertSame(['The Atelier Edit', 'The Shopping Experience', 'The Signature Bag'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query('.//h3', $section))));
        $this->assertSame(1, $xpath->query('./div[contains(@class,"wt-hot-sale-grid")]', $section)->length);
        $this->assertStringContainsString('Historical heading', $section->textContent);
        $this->assertStringContainsString('grid-template-columns:repeat(2,minmax(0,1fr))', $html);
        $this->assertStringContainsString('grid-column:1 / -1', $html);
    }

    public function test_invalid_media_preserves_content_and_unavailable_config_falls_back_safely(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $images = [$this->image('atelier'), $this->image('experience'), $this->image('signature')];
        $payload = $this->payload($homepage, $images);
        $payload['hot_sale_tile_1_title'] = 'Preserved title';
        $payload['hot_sale_tile_2_media_id'] = 'missing-media';
        $this->actingAs($this->manager)->from(route('admin.homepage.hot-sale.edit'))->put(route('admin.homepage.hot-sale.update'), $payload)
            ->assertRedirect(route('admin.homepage.hot-sale.edit'))->assertSessionHasErrors('hot_sale_tile_2_media_id')
            ->assertSessionHasInput('hot_sale_tile_1_title', 'Preserved title');

        $this->actingAs($this->manager)->put(route('admin.homepage.hot-sale.update'), $this->payload($homepage->fresh(), $images))->assertRedirect();
        $images[1]->forceFill(['archived_at' => now()])->save();
        $this->get(route('home'))->assertOk()->assertSee('data-homepage-hot-sale', false)->assertSeeText('The Atelier Edit');
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk()
            ->assertSeeText('One or more editorial feature tiles needs attention.')
            ->assertSeeText('Needs attention');
    }

    public function test_contextual_alt_is_optional_and_anomalous_ready_media_returns_actionable_error(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $images = [$this->image('atelier'), $this->image('experience'), $this->image('signature')];
        $payload = $this->payload($homepage, $images);
        $payload['hot_sale_tile_1_alt_override'] = 'Editorial tailoring in the atelier';

        $this->actingAs($this->manager)->put(route('admin.homepage.hot-sale.update'), $payload)->assertRedirect();
        $this->assertDatabaseHas('media_usages', [
            'media_asset_id' => $images[0]->id,
            'alt_text_override' => 'Editorial tailoring in the atelier',
        ]);
        $this->get(route('home'))->assertOk()->assertSee('alt="Editorial tailoring in the atelier"', false);

        $images[1]->forceFill(['default_alt_text' => null])->save();
        $payload = $this->payload($homepage->fresh(), $images);
        $payload['hot_sale_tile_1_title'] = 'Preserved after alt failure';
        $response = $this->actingAs($this->manager)->from(route('admin.homepage.hot-sale.edit'))
            ->put(route('admin.homepage.hot-sale.update'), $payload);
        $response->assertRedirect(route('admin.homepage.hot-sale.edit'))
            ->assertSessionHasErrors(['hot_sale_tile_2_media_id' => 'This Media Asset needs descriptive alt text before it can be used here. Update it in Media Library.'])
            ->assertSessionHasInput('hot_sale_tile_1_title', 'Preserved after alt failure')
            ->assertSessionHasInput('hot_sale_tile_2_media_id', $images[1]->id);
    }

    /** @param array<int, MediaAsset> $images @return array<string, mixed> */
    private function payload(HomepageHero $homepage, array $images): array
    {
        $payload = [...HomepageHero::hotSaleDefaults(), 'lock_version' => $homepage->lock_version];
        foreach ([1, 2, 3] as $position) {
            $payload["hot_sale_tile_{$position}_media_id"] = $images[$position - 1]->id;
        }

        return $payload;
    }

    private function image(string $name): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'homepage/hot-sale/'.$id,
            'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg',
            'original_filename' => $name.'.jpg', 'internal_title' => $name, 'default_alt_text' => ucfirst($name).' editorial image',
            'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false,
            'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => now(),
        ]);
    }

    private function video(string $name): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'homepage/hot-sale/'.$id,
            'resource_type' => MediaResourceType::Video, 'format' => 'mp4', 'mime_type' => 'video/mp4',
            'original_filename' => $name.'.mp4', 'internal_title' => $name, 'default_alt_text' => ucfirst($name).' editorial video',
            'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false,
            'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => now(),
        ]);
    }
}
