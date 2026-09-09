<?php

namespace Tests\Feature\Homepage;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageHandbagsPresenter;
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

final class HomepageHandbagsManagementTest extends TestCase
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

    public function test_editor_authority_validation_save_and_unmanaged_fallback(): void
    {
        $edit = route('admin.homepage.handbags.edit');
        $update = route('admin.homepage.handbags.update');
        $this->get($edit)->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->get($edit)->assertForbidden();
        $this->put($update, [])->assertForbidden();
        $this->actingAs($this->manager)->get($edit)->assertOk()->assertSeeText('Using storefront default')->assertSee('data-collection-picker', false)->assertSee('data-media-picker', false)->assertSeeText('Back to Homepage');
        $base = [...HomepageHero::handbagsDefaults(), 'lock_version' => HomepageHero::query()->sole()->lock_version, 'handbags_managed' => '1', 'handbags_heading' => 'A curated selection'];
        $this->from($edit)->put($update, $base)->assertSessionHasErrors(['handbags_collection_id', 'handbags_image_1', 'handbags_image_2'])->assertSessionHasInput('handbags_heading', 'A curated selection');
        $this->put($update, [...$base, 'handbags_managed' => '0'])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertSeeText('Savanna Tote Bag')->assertDontSee('<template id="homepage-handbags-projection">', false);
        $this->get($edit)->assertSee('A curated selection')->assertSeeText('Using storefront default');
        $this->put($update, [...$base, 'handbags_managed' => '0'])->assertSessionHasErrors('lock_version');
    }

    public function test_canonical_order_capacity_prices_media_and_managed_runtime_projection(): void
    {
        $products = [];
        foreach (range(1, 7) as $number) {
            $products[] = $this->product('Canonical Bag '.$number, 'canonical-bag-'.$number, true);
        }
        $hidden = $this->product('Unavailable Bag', 'unavailable-bag', false);
        $collection = $this->collection('Handbag Selection', [$products[2], $hidden, $products[0], $products[1], ...array_slice($products, 3)], true);
        $one = $this->image('hero-one', 'Canonical hero one');
        $two = $this->image('hero-two', 'Canonical hero two');
        $this->get(route('admin.homepage.handbags.edit'))->assertOk();
        $base = [...HomepageHero::handbagsDefaults(), 'lock_version' => HomepageHero::query()->sole()->lock_version, 'handbags_managed' => '1', 'handbags_collection_id' => $collection->id, 'handbags_heading' => 'Managed Handbags', 'handbags_hero_copy' => 'Managed editorial copy', 'handbags_image_1' => $one->id, 'handbags_image_2' => $two->id, 'handbags_alt_2' => 'Contextual second slide'];
        $this->put(route('admin.homepage.handbags.update'), $base)->assertSessionHasNoErrors()->assertRedirect(route('admin.homepage.handbags.edit'));
        $this->assertDatabaseHas('homepage_heroes', ['handbags_collection_id' => $collection->id, 'handbags_managed' => true]);
        $this->assertDatabaseHas('audit_records', ['action' => 'homepage.handbags.updated']);
        $presented = app(HomepageHandbagsPresenter::class)->present();
        $this->assertCount(6, $presented['products']);
        $this->assertSame(7, $presented['ready_count']);
        $this->assertSame(1, $presented['attention_count']);
        $this->assertSame(['Canonical Bag 3', 'Canonical Bag 1', 'Canonical Bag 2', 'Canonical Bag 4', 'Canonical Bag 5', 'Canonical Bag 6'], array_column($presented['products'], 'title'));
        $this->assertSame(['Canonical hero one', 'Contextual second slide'], array_column($presented['slides'], 'alt'));
        $response = $this->get(route('home'))->assertOk()->assertSeeText('Managed Handbags')->assertSeeText('Managed editorial copy')->assertSeeText('TZS 125,000')->assertSee(route('collections.show', $collection), false)->assertSee(route('products.show', $products[2]), false)->assertSee('homepage-handbags-projection', false)->assertSee('synchronizeHandbags', false)->assertSee('wt-handbags-products', false)->assertDontSeeText('Savanna Tote Bag')->assertDontSeeText('Unavailable Bag')->assertDontSeeText('Canonical Bag 7');
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Managed Handbags'), strpos($html, 'Complimentary Delivery in Dar es Salaam'));
        $this->assertLessThan(strpos($html, 'Client Stories'), strpos($html, 'Managed Handbags'));
        $this->assertSame(2, substr_count($html, 'data-homepage-handbags class='));
        $this->get(route('admin.homepage.handbags.edit'))->assertOk()->assertSeeText('Using managed content from Administration')->assertSeeText('Handbag Selection');
        $this->get(route('admin.homepage.edit'))->assertOk()->assertSeeTextInOrder(['Manage Complimentary Delivery', "Manage Women's Handbags"]);
        $this->get(route('collections.show', $collection))->assertOk();
        $this->get(route('products.show', $products[2]))->assertOk();
        foreach (array_slice($products, 1) as $product) {
            $product->forceFill(['catalogue_status' => 'draft'])->save();
        }
        $this->assertCount(1, app(HomepageHandbagsPresenter::class)->present()['products']);
        $defaultVariant = $products[0]->default_variant_id;
        $products[0]->forceFill(['default_variant_id' => null])->save();
        $this->assertCount(0, app(HomepageHandbagsPresenter::class)->present()['products']);
        $products[0]->forceFill(['default_variant_id' => $defaultVariant, 'base_price_minor' => null])->save();
        $this->assertCount(0, app(HomepageHandbagsPresenter::class)->present()['products']);
        $products[0]->forceFill(['base_price_minor' => 12500000, 'archived_at' => now()])->save();
        $this->assertCount(0, app(HomepageHandbagsPresenter::class)->present()['products']);
        $products[0]->forceFill(['archived_at' => null])->save();
        $products[0]->forceFill(['catalogue_status' => 'draft'])->save();
        $this->assertCount(0, app(HomepageHandbagsPresenter::class)->present()['products']);
        $this->get(route('home'))->assertDontSeeText('Savanna Tote Bag')->assertSeeText('Managed Handbags');
        $one->forceFill(['archived_at' => now()])->save();
        $this->assertCount(1, app(HomepageHandbagsPresenter::class)->present()['slides']);
        $two->forceFill(['archived_at' => now()])->save();
        $this->get(route('home'))->assertSee('data-homepage-handbags hidden', false)->assertDontSeeText('Savanna Tote Bag');
        $collection->forceFill(['catalogue_status' => 'draft'])->save();
        $this->put(route('admin.homepage.handbags.update'), [...$base, 'lock_version' => HomepageHero::query()->sole()->lock_version])->assertSessionHasErrors(['handbags_collection_id', 'handbags_image_1', 'handbags_image_2']);
    }

    public function test_alt_override_is_optional_only_when_the_image_has_default_alt(): void
    {
        $collection = $this->collection('Alt Selection', [], true);
        $image = $this->image('editorial', 'Default editorial description');
        $this->get(route('admin.homepage.handbags.edit'))->assertOk()
            ->assertSeeText('Optional when the selected image has default alt text in Media Library.')
            ->assertDontSeeText('Contextual alt override (optional)');
        $data = [...HomepageHero::handbagsDefaults(), 'handbags_managed' => '1', 'handbags_collection_id' => $collection->id, 'handbags_image_1' => $image->id, 'handbags_image_2' => $image->id, 'handbags_alt_1' => '', 'handbags_alt_2' => '', 'lock_version' => HomepageHero::query()->sole()->lock_version];
        $this->put(route('admin.homepage.handbags.update'), $data)->assertSessionHasNoErrors();
        $this->assertSame('Default editorial description', app(HomepageHandbagsPresenter::class)->present()['slides'][0]['alt']);
        $image->forceFill(['default_alt_text' => null])->save();
        $data['lock_version'] = HomepageHero::query()->sole()->lock_version;
        $this->from(route('admin.homepage.handbags.edit'))->put(route('admin.homepage.handbags.update'), $data)
            ->assertSessionHasErrors(['handbags_alt_1' => 'This image has no default alt text. Enter an override here or add default alt text in Media Library.', 'handbags_alt_2' => 'This image has no default alt text. Enter an override here or add default alt text in Media Library.'])
            ->assertSessionDoesntHaveErrors(['handbags_image_1', 'handbags_image_2'])
            ->assertSessionHasInput('handbags_image_1', $image->id);
        $this->put(route('admin.homepage.handbags.update'), [...$data, 'handbags_alt_1' => 'Editorial image one', 'handbags_alt_2' => 'Editorial image two'])->assertSessionHasNoErrors();
        $this->assertSame(['Editorial image one', 'Editorial image two'], array_column(app(HomepageHandbagsPresenter::class)->present()['slides'], 'alt'));
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
