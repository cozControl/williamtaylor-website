<?php

namespace Tests\Feature\Homepage;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Support\CollectionCardPresenter;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageExploreCollectionsPresenter;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HomepageExploreCollectionsManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::CMS_MANAGER));
        $this->category = ProductCategory::query()->create([
            'name' => 'Explore',
            'slug' => 'explore',
            'is_visible' => true,
            'position' => 0,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);
    }

    public function test_editor_and_bounded_searchable_picker_are_authorized(): void
    {
        $this->get(route('admin.homepage.explore-collections.edit'))->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ordinary)->get(route('admin.homepage.explore-collections.edit'))->assertForbidden();

        $collection = $this->collection('Searchable Menswear', [], true);
        $this->actingAs($this->manager)->get(route('admin.homepage.explore-collections.edit'))
            ->assertOk()
            ->assertSeeText('Three supplied positions')
            ->assertSeeText('Position 3')
            ->assertDontSeeText('Position 4')
            ->assertSeeText('Search Collections')
            ->assertSeeText('Back')
            ->assertSeeText('Save changes');
        $this->actingAs($this->manager)->getJson(route('admin.homepage.collection-picker', ['search' => 'Searchable']))
            ->assertOk()
            ->assertJsonPath('data.0.id', $collection->id)
            ->assertJsonPath('data.0.slug', 'searchable-menswear')
            ->assertJsonPath('data.0.visibility', 'Visible')
            ->assertJsonPath('data.0.ready_count', 0)
            ->assertJsonPath('data.0.status', 'Needs attention')
            ->assertJsonCount(1, 'data');
    }

    public function test_save_preserves_order_and_projects_canonical_collection_cards(): void
    {
        $ready = $this->product('Ready Piece', 'ready-piece', true);
        $hidden = $this->product('Hidden Piece', 'hidden-piece', false);
        $menswear = $this->collection('Menswear', [$ready, $hidden], true, 'The definitive wardrobe.', 'Homepage-specific alt');
        $unisex = $this->collection('Unisex', [], true, 'Gender-free dressing.');
        $accessories = $this->collection('Accessories', [$ready], true, 'The details that define style.');
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();

        $this->actingAs($this->manager)->put(route('admin.homepage.explore-collections.update'), [
            'lock_version' => $homepage->lock_version,
            'explore_collections_managed' => '1',
            'explore_collections_eyebrow' => 'Browse the House',
            'explore_collections_heading' => 'Find Your Collection',
            'explore_collection_1_id' => $unisex->id,
            'explore_collection_2_id' => $menswear->id,
            'explore_collection_3_id' => $accessories->id,
        ])->assertRedirect(route('admin.homepage.explore-collections.edit'))
            ->assertSessionHas('status', 'Explore the Collection updated successfully.');

        $homepage->refresh();
        $this->assertTrue($homepage->explore_collections_managed);
        $this->assertSame([$unisex->id, $menswear->id, $accessories->id], [
            $homepage->explore_collection_1_id,
            $homepage->explore_collection_2_id,
            $homepage->explore_collection_3_id,
        ]);
        $section = app(HomepageExploreCollectionsPresenter::class)->present();
        $this->assertSame(HomepageExploreCollectionsPresenter::CAPACITY, 3);
        $this->assertSame(['Unisex', 'Menswear', 'Accessories'], collect($section['collections'])->pluck('title')->all());
        $this->assertSame([0, 2, 1], collect($section['collections'])->pluck('product_count')->all());
        $this->assertSame([0, 1, 1], collect($section['collections'])->pluck('ready_count')->all());

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('data-homepage-explore-collections', false)
            ->assertSeeText('Browse the House')
            ->assertSeeText('Find Your Collection')
            ->assertSeeTextInOrder(['Unisex', 'Menswear', 'Accessories'])
            ->assertSeeText('The definitive wardrobe.')
            ->assertSee('alt="Homepage-specific alt"', false)
            ->assertSee(route('collections.show', $menswear), false)
            ->assertSee('grid-cols-1 md:grid-cols-3 gap-4', false)
            ->assertSee('aspect-[3/4]', false)
            ->assertSee('group-hover:scale-108', false)
            ->assertSee('homepage-explore-collections-projection', false)
            ->assertDontSee('data-homepage-explore-product-count', false);
        $response->assertSee('Explore &rarr;', false);
        $this->assertDatabaseHas('audit_records', [
            'action' => 'homepage.explore_collections.updated',
            'resource_identifier' => $homepage->id,
        ]);
    }

    public function test_duplicates_and_unavailable_collections_are_rejected_with_state_preserved(): void
    {
        $visible = $this->collection('Visible Collection', [], true);
        $hidden = $this->collection('Hidden Collection', [], false);
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $base = [
            'lock_version' => $homepage->lock_version,
            'explore_collections_managed' => '1',
            'explore_collections_eyebrow' => 'Shop By Category',
            'explore_collections_heading' => 'Explore the Collection',
        ];

        $this->actingAs($this->manager)->from(route('admin.homepage.explore-collections.edit'))
            ->put(route('admin.homepage.explore-collections.update'), [
                ...$base,
                'explore_collection_1_id' => $visible->id,
                'explore_collection_2_id' => $visible->id,
                'explore_collection_3_id' => null,
            ])->assertRedirect(route('admin.homepage.explore-collections.edit'))
            ->assertSessionHasErrors(['explore_collection_1_id', 'explore_collection_2_id'])
            ->assertSessionHasInput('explore_collection_1_id', $visible->id);

        $this->actingAs($this->manager)->from(route('admin.homepage.explore-collections.edit'))
            ->put(route('admin.homepage.explore-collections.update'), [
                ...$base,
                'explore_collection_1_id' => $hidden->id,
                'explore_collection_2_id' => null,
                'explore_collection_3_id' => null,
            ])->assertRedirect(route('admin.homepage.explore-collections.edit'))
            ->assertSessionHasErrors('explore_collection_1_id')
            ->assertSessionHasInput('explore_collection_1_id', $hidden->id);

        $this->assertFalse(HomepageHero::query()->sole()->explore_collections_managed);
    }

    public function test_presenter_filters_visibility_uses_effective_alt_and_supports_zero_partial_and_full_states(): void
    {
        $product = $this->product('Public Piece', 'public-piece', true);
        $first = $this->collection('First Collection', [$product], true, 'First description.', 'First override alt');
        $second = $this->collection('Second Collection', [], true);
        $third = $this->collection('Third Collection', [$product], true);
        $card = app(CollectionCardPresenter::class)->present($first);

        $this->assertTrue($card['eligible']);
        $this->assertSame('First override alt', $card['image']['alt']);
        $this->assertSame(1, $card['ready_count']);
        $this->assertSame(route('collections.show', $first), $card['url']);

        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $homepage->forceFill([
            ...HomepageHero::exploreCollectionsDefaults(),
            'explore_collections_managed' => true,
            'explore_collection_1_id' => $first->id,
            'explore_collection_2_id' => $second->id,
            'explore_collection_3_id' => $third->id,
        ])->save();
        $full = app(HomepageExploreCollectionsPresenter::class)->present();
        $this->assertSame(3, $full['configured_count']);
        $this->assertSame(3, $full['eligible_count']);

        $second->forceFill(['catalogue_status' => 'draft'])->save();
        $partial = app(HomepageExploreCollectionsPresenter::class)->present();
        $this->assertSame(3, $partial['configured_count']);
        $this->assertSame(2, $partial['eligible_count']);
        $this->assertSame(['First Collection', 'Third Collection'], collect($partial['collections'])->pluck('title')->all());
        $this->get(route('home'))->assertOk()->assertDontSeeText('Second Collection')->assertSeeText('First Collection')->assertSeeText('Third Collection');

        $homepage->forceFill([
            'explore_collection_1_id' => null,
            'explore_collection_2_id' => null,
            'explore_collection_3_id' => null,
        ])->save();
        $zero = app(HomepageExploreCollectionsPresenter::class)->present();
        $this->assertTrue($zero['managed']);
        $this->assertSame(0, $zero['configured_count']);
        $this->assertSame([], $zero['collections']);
        $this->get(route('home'))->assertOk()
            ->assertSee('data-homepage-explore-collections', false)
            ->assertDontSee('data-homepage-explore-collection=', false);
    }

    public function test_unmanaged_fallback_and_bounded_public_regressions_remain_available(): void
    {
        $product = $this->product('Regression Product', 'regression-product', true);
        $collection = $this->collection('Regression Collection', [$product], true);

        $this->get(route('home'))->assertOk()
            ->assertSeeText('William Taylor')
            ->assertSeeText('New Arrivals')
            ->assertSeeText("William's Hot Sale")
            ->assertSeeText('The Future of Style')
            ->assertSeeText('LIMITED EDITION')
            ->assertSeeText('Explore the Collection')
            ->assertSeeText("Men's Wear")
            ->assertDontSee('<template id="homepage-explore-collections-projection">', false);
        $this->get(route('collections.show', $collection))->assertOk()->assertSeeText('Regression Collection');
        $this->get(route('products.show', $product))->assertOk()->assertSeeText('Regression Product');

        MediaUsage::query()->where('owner_type', Collection::class)->where('owner_identifier', $collection->id)->delete();
        $this->assertFalse(app(CollectionCardPresenter::class)->present($collection->fresh())['eligible']);
    }

    public function test_managed_response_contains_exact_cards_in_visible_section_and_projection(): void
    {
        $collections = collect(['Canonical Alpha', 'Canonical Bravo', 'Canonical Charlie'])
            ->map(fn (string $name) => $this->collection($name, [], true));
        $this->actingAs($this->manager)->get(route('admin.homepage.explore-collections.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $this->put(route('admin.homepage.explore-collections.update'), [
            ...HomepageHero::exploreCollectionsDefaults(),
            'lock_version' => $homepage->lock_version,
            'explore_collections_managed' => '1',
            'explore_collection_1_id' => $collections[0]->id,
            'explore_collection_2_id' => $collections[1]->id,
            'explore_collection_3_id' => $collections[2]->id,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($homepage->fresh()->explore_collections_managed);
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertIsString($html);
        preg_match_all('/<section[^>]*data-homepage-explore-collections[^>]*>.*?<\/section>/s', $html, $sections);
        $this->assertCount(2, $sections[0]);
        foreach ($sections[0] as $section) {
            preg_match_all('/<h3[^>]*>(.*?)<\/h3>/s', $section, $titles);
            $this->assertSame(['Canonical Alpha', 'Canonical Bravo', 'Canonical Charlie'], array_map('trim', $titles[1]));
            $this->assertSame(3, substr_count($section, 'data-homepage-explore-collection='));
            foreach ($collections as $collection) {
                $card = app(CollectionCardPresenter::class)->present($collection);
                $this->assertStringContainsString(e($card['url']), $section);
                $this->assertStringContainsString(e($card['image']['url']), $section);
            }
            $this->assertStringNotContainsString('html/mens-wear.html', $section);
            $this->assertStringNotContainsString('html/unisex.html', $section);
            $this->assertStringNotContainsString('html/accessories.html', $section);
        }
        preg_match('/<template id="homepage-explore-collections-projection">(.*?)<\/template>/s', $html, $projection);
        $this->assertArrayHasKey(1, $projection);
        $this->assertSame($sections[0][1], trim($projection[1] ?? ''));
        $this->assertStringContainsString("document.getElementById('homepage-explore-collections-projection')", $html);
        $this->assertStringContainsString('section.dataset.homepageExploreCollections', $html);
        $this->assertStringContainsString('synchronizeExploreCollections();', $html);
        $this->get(route('admin.homepage.explore-collections.edit'))->assertSeeText('USING MANAGED COLLECTIONS');
    }

    public function test_saved_selections_do_not_activate_unmanaged_mode_and_editor_explains_it(): void
    {
        $collection = $this->collection('Unpublished Selection', [], true);
        $this->actingAs($this->manager)->get(route('admin.homepage.explore-collections.edit'))->assertOk();
        $this->put(route('admin.homepage.explore-collections.update'), [
            ...HomepageHero::exploreCollectionsDefaults(),
            'lock_version' => HomepageHero::query()->sole()->lock_version,
            'explore_collection_1_id' => $collection->id,
        ])->assertSessionHasNoErrors();
        $homepage = HomepageHero::query()->sole();
        $this->assertFalse($homepage->explore_collections_managed);
        $this->assertSame($collection->id, $homepage->getAttribute('explore_collection_1_id'));
        $this->get(route('admin.homepage.explore-collections.edit'))
            ->assertSeeText('USING STOREFRONT DEFAULT')
            ->assertSeeText('Selected Collections below are not currently published because managed content is off.');
        $this->get(route('home'))->assertOk()->assertDontSeeText('Unpublished Selection')
            ->assertSee('html/mens-wear.html', false)
            ->assertDontSee('<template id="homepage-explore-collections-projection">', false);
    }

    /** @param list<Product> $products */
    private function collection(string $name, array $products, bool $visible, string $description = 'Canonical Collection description.', ?string $altOverride = null): Collection
    {
        $image = $this->image(Str::slug($name), $name.' canonical alt');
        $ids = collect($products)->pluck('id')->values()->all();
        $orders = collect($ids)->mapWithKeys(fn (string $id, int $position): array => [$id => $position])->all();
        $this->actingAs($this->manager)->post(route('admin.collections.store'), [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $description,
            'media_asset_id' => $image->id,
            'media_alt' => $altOverride,
            'product_ids' => $ids,
            'product_order' => $orders,
            'visibility' => $visible ? 'visible' : 'hidden',
        ])->assertRedirect();

        return Collection::query()->where('slug', Str::slug($name))->sole();
    }

    private function product(string $title, string $slug, bool $active): Product
    {
        $image = $this->image($slug, $title);
        $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => $title,
            'slug' => $slug,
            'short_description' => 'Canonical Product.',
            'currency' => 'TZS',
            'base_price' => '125,000',
            'primary_category_id' => $this->category->id,
            'primary_media_id' => $image->id,
            'media_alt' => [$image->id => $title],
            'gallery_media_ids' => [],
            'draft_variants' => [[
                'key' => 'none--none',
                'colour_key' => '',
                'size_key' => '',
                'label' => 'Default',
                'sku' => 'WT-'.strtoupper($slug),
                'price' => '',
            ]],
            'default_variant_key' => 'none--none',
            'status' => $active ? 'active' : 'hidden',
        ])->assertRedirect();

        return Product::query()->where('slug', $slug)->sole();
    }

    private function image(string $name, string $alt): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id,
            'provider_asset_id' => 'asset-'.$id,
            'provider_public_id' => 'homepage/'.$id,
            'resource_type' => MediaResourceType::Image,
            'format' => 'jpg',
            'mime_type' => 'image/jpeg',
            'original_filename' => $name.'.jpg',
            'internal_title' => $name,
            'default_alt_text' => $alt,
            'accessibility_classification' => AccessibilityClassification::Informative,
            'is_decorative' => false,
            'state' => MediaAssetState::Ready,
            'bytes' => 1000,
            'uploaded_by' => $this->manager->id,
            'confirmed_at' => now(),
        ]);
    }
}
