<?php

namespace Tests\Feature\Homepage;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
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
use Tests\Support\CategoryOwner;
use Tests\TestCase;

final class HomepageSummerEditManagementTest extends TestCase
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
        $this->category = ProductCategory::query()->create(['collection_id' => CategoryOwner::for($this->manager->id)->id,
            'name' => 'Explore',
            'slug' => 'explore',
            'is_visible' => true,
            'position' => 0,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);
    }

    public function test_summer_editor_authority_validation_and_managed_projection(): void
    {
        $this->get(route('admin.homepage.summer-edit.edit'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get(route('admin.homepage.summer-edit.edit'))->assertForbidden();
        $collection = $this->collection('Seasonal Selection', [], true);
        $this->actingAs($this->manager)->get(route('admin.homepage.summer-edit.edit'))->assertOk()->assertSeeText('Using storefront default')->assertSee('admin-panel', false)->assertSeeText('Back to Homepage')->assertSeeText('Save changes');
        $base = [...HomepageHero::summerEditDefaults(), 'lock_version' => HomepageHero::query()->sole()->lock_version, 'summer_edit_managed' => '1', 'summer_edit_heading' => 'Managed seasonal feature'];
        $this->put(route('admin.homepage.summer-edit.update'), $base)->assertSessionHasErrors('summer_edit_collection_id')->assertSessionHasInput('summer_edit_heading', 'Managed seasonal feature');
        $this->put(route('admin.homepage.summer-edit.update'), [...$base, 'summer_edit_collection_id' => $collection->id])->assertSessionHasNoErrors()->assertRedirect(route('admin.homepage.summer-edit.edit'));
        $this->assertDatabaseHas('homepage_heroes', ['summer_edit_collection_id' => $collection->id, 'summer_edit_managed' => true]);
        $this->get(route('admin.homepage.summer-edit.edit'))->assertSeeText('Using managed content from Administration')->assertSee('Managed seasonal feature');
        $response = $this->get(route('home'))->assertOk()->assertSeeText('Managed seasonal feature')->assertSee(route('collections.show', $collection), false)->assertSee('homepage-summer-edit-projection', false)->assertSeeText('Complimentary Delivery in Dar es Salaam')->assertSee('homepage-future-style-projection', false);
        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertLessThan(strpos($html, 'Managed seasonal feature'), strpos($html, 'Explore the Collection'));
        $this->assertLessThan(strpos($html, 'Complimentary Delivery in Dar es Salaam'), strpos($html, 'Managed seasonal feature'));
        $this->assertSame(2, substr_count($html, 'data-homepage-summer-edit>'));
        $this->assertStringNotContainsString('filter=sale', $html);
        $this->get(route('collections.show', $collection))->assertOk();
        $this->get(route('admin.homepage.edit'))->assertSeeText('Manage The Summer Edit');
        $collection->forceFill(['catalogue_status' => 'draft'])->save();
        $this->get(route('home'))->assertDontSeeText('Managed seasonal feature')->assertSee('data-homepage-summer-edit hidden', false)->assertSeeText('Complimentary Delivery in Dar es Salaam');
        $this->get(route('admin.homepage.summer-edit.edit'))->assertSeeText('Needs attention');
        $this->put(route('admin.homepage.summer-edit.update'), [...$base, 'lock_version' => HomepageHero::query()->sole()->lock_version, 'summer_edit_collection_id' => $collection->id])->assertSessionHasErrors('summer_edit_collection_id');
    }

    public function test_unmanaged_retains_template_and_preserves_saved_copy(): void
    {
        $this->actingAs($this->manager)->get(route('admin.homepage.summer-edit.edit'))->assertOk();
        $this->put(route('admin.homepage.summer-edit.update'), [...HomepageHero::summerEditDefaults(), 'lock_version' => HomepageHero::query()->sole()->lock_version, 'summer_edit_heading' => 'Not published yet'])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertSeeText('The Summer Edit')->assertSee('filter=sale', false)->assertDontSeeText('Not published yet')->assertDontSee('<template id="homepage-summer-edit-projection">', false);
        $this->get(route('admin.homepage.summer-edit.edit'))->assertSee('Not published yet')->assertSeeText('Using storefront default');
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
