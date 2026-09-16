<?php

namespace Tests\Feature\Homepage;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductBadge;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageNewArrivalsPresenter;
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
use Tests\Support\CategoryOwner;
use Tests\TestCase;

final class HomepageNewArrivalsManagementTest extends TestCase
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
        $this->category = ProductCategory::query()->create(['collection_id' => CategoryOwner::for($this->manager->id)->id, 'name' => 'New', 'slug' => 'new', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
    }

    public function test_editor_and_searchable_picker_are_authorized_and_show_source_feedback(): void
    {
        $this->get(route('admin.homepage.new-arrivals.edit'))->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ordinary)->get(route('admin.homepage.new-arrivals.edit'))->assertForbidden();

        $product = $this->product('Picker Product', 'picker-product', '125,000');
        $collection = $this->collection('Picker Collection', [$product]);
        $this->actingAs($this->manager)->getJson(route('admin.homepage.collection-picker', ['search' => 'Picker']))
            ->assertOk()->assertJsonPath('data.0.id', $collection->id)->assertJsonPath('data.0.product_count', 1)->assertJsonPath('data.0.ready_count', 1);
        $this->actingAs($this->manager)->get(route('admin.homepage.new-arrivals.edit'))->assertOk()
            ->assertSee('data-admin-ui-revision="ecom-home-2e"', false)
            ->assertSeeText('Change Collection')->assertSeeText('Back to Homepage')->assertSeeText('Save changes');
    }

    public function test_save_projects_ordered_eligible_canonical_cards_and_collection_cta(): void
    {
        $second = $this->product('Second Arrival', 'second-arrival', '225,000');
        $first = $this->product('First Arrival', 'first-arrival', '175,000');
        $hidden = $this->product('Hidden Arrival', 'hidden-arrival', '99,000', false);
        ProductBadge::query()->create(['id' => (string) Str::ulid(), 'product_id' => $first->id, 'badge_key' => 'new', 'position' => 0, 'active_key' => $first->id.':new', 'position_key' => $first->id.':0']);
        $collection = $this->collection('Canonical New Arrivals', [$first, $second, $hidden]);
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();

        $this->actingAs($this->manager)->put(route('admin.homepage.new-arrivals.update'), [
            'lock_version' => $homepage->lock_version,
            'new_arrivals_eyebrow' => 'Fresh from the Atelier',
            'new_arrivals_heading' => 'Latest Pieces',
            'new_arrivals_cta_label' => 'View the Collection',
            'new_arrivals_collection_id' => $collection->id,
        ])->assertRedirect(route('admin.homepage.new-arrivals.edit'))->assertSessionHas('status', 'New Arrivals updated successfully.');

        $response = $this->get(route('home'))->assertOk()
            ->assertSee('data-homepage-new-arrivals', false)
            ->assertSeeText('Fresh from the Atelier')->assertSeeText('Latest Pieces')->assertSeeText('View the Collection')
            ->assertSeeTextInOrder(['First Arrival', 'Second Arrival'])
            ->assertSeeText('TZS 175,000')->assertSeeText('NEW')
            ->assertSee(route('products.show', $first), false)
            ->assertSee(route('collections.show', $collection), false)
            ->assertDontSeeText('Hidden Arrival')
            ->assertSee('homepage-new-arrivals-projection', false)
            ->assertSee('data-storefront-product-card', false);
        $this->assertDatabaseHas('audit_records', ['action' => 'homepage.new-arrivals.updated', 'resource_identifier' => $homepage->id]);
        $response->assertSee('aspect-[3/4]', false)->assertSee('md:grid-cols-4', false)->assertSee('group-hover:scale-105', false)
            ->assertSee('wt-new-arrivals-product-grid', false)->assertSee('data-storefront-grid-spacing="ecom-home-3a"', false);
        $this->assertSame(8, HomepageNewArrivalsPresenter::CARD_LIMIT);
        $this->assertCount(2, app(HomepageNewArrivalsPresenter::class)->present()['products']);
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk()
            ->assertSeeText('Source: Canonical New Arrivals. 2 storefront-ready Products')
            ->assertSeeText('1 Product needs attention.')
            ->assertSeeText('Needs attention');
    }

    public function test_invalid_hidden_source_is_rejected_and_unconfigured_homepage_keeps_static_fallback(): void
    {
        $hidden = $this->collection('Hidden Collection', [], false);
        $this->actingAs($this->manager)->get(route('admin.homepage.edit'))->assertOk();
        $homepage = HomepageHero::query()->sole();
        $this->actingAs($this->manager)->put(route('admin.homepage.new-arrivals.update'), [
            'lock_version' => $homepage->lock_version,
            ...HomepageHero::newArrivalsDefaults(),
            'new_arrivals_collection_id' => $hidden->id,
        ])->assertSessionHasErrors('new_arrivals_collection_id');

        $homepage->delete();
        $this->get(route('home'))->assertOk()->assertSeeText('Just Arrived')->assertSeeText('New Arrivals')->assertDontSee('<template id="homepage-new-arrivals-projection">', false);
    }

    private function product(string $title, string $slug, string $price, bool $active = true): Product
    {
        $image = $this->image($slug);
        $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => $title, 'slug' => $slug, 'short_description' => 'Canonical Product.', 'currency' => 'TZS', 'base_price' => $price,
            'primary_category_id' => $this->category->id, 'primary_media_id' => $image->id, 'media_alt' => [$image->id => $title],
            'gallery_media_ids' => [], 'draft_variants' => [['key' => 'none--none', 'colour_key' => '', 'size_key' => '', 'label' => 'Default', 'sku' => 'WT-'.strtoupper($slug), 'price' => '']],
            'default_variant_key' => 'none--none', 'status' => $active ? 'active' : 'hidden',
        ])->assertRedirect();

        return Product::query()->where('slug', $slug)->sole();
    }

    /** @param list<Product> $products */
    private function collection(string $name, array $products, bool $visible = true): Collection
    {
        $image = $this->image(Str::slug($name));
        $ids = collect($products)->pluck('id')->values()->all();
        $orders = collect($ids)->mapWithKeys(fn (string $id, int $position) => [$id => $position])->all();
        $this->actingAs($this->manager)->post(route('admin.collections.store'), [
            'name' => $name, 'slug' => Str::slug($name), 'description' => 'Homepage source Collection.',
            'media_asset_id' => $image->id, 'media_alt' => $name, 'product_ids' => $ids, 'product_order' => $orders,
            'visibility' => $visible ? 'visible' : 'hidden',
        ])->assertRedirect();

        return Collection::query()->where('slug', Str::slug($name))->sole();
    }

    private function image(string $name): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'homepage/'.$id,
            'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg',
            'original_filename' => $name.'.jpg', 'internal_title' => $name, 'default_alt_text' => $name,
            'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false,
            'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => now(),
        ]);
    }
}
