<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Actions\CreateProduct;
use App\Domain\Catalogue\Actions\CreateProductRevision;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Catalogue\Support\ProductPresenter;
use App\Domain\Catalogue\Support\ProductPrice;
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

final class EcomCatalogueCoreTest extends TestCase
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

    public function test_category_admin_creates_hierarchy_and_enforces_unique_slug_and_authorization(): void
    {
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ordinary)->post(route('admin.product-categories.store'), [])->assertForbidden();

        $this->actingAs($this->manager)->post(route('admin.product-categories.store'), [
            'name' => 'Men', 'slug' => 'men', 'parent_id' => null, 'description' => 'Menswear', 'is_visible' => '1', 'position' => 0,
        ])->assertRedirect();
        $parent = ProductCategory::query()->where('slug', 'men')->sole();
        $this->actingAs($this->manager)->post(route('admin.product-categories.store'), [
            'name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $parent->id, 'description' => 'Shirts', 'is_visible' => '0', 'position' => 1,
        ])->assertRedirect();

        $child = ProductCategory::query()->where('slug', 'shirts')->sole();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertFalse($child->is_visible);
        $this->actingAs($this->manager)->post(route('admin.product-categories.store'), [
            'name' => 'Duplicate', 'slug' => 'shirts', 'is_visible' => '1', 'position' => 2,
        ])->assertSessionHasErrors('slug');

        $this->actingAs($this->manager)->patch(route('admin.product-categories.archive', $child))->assertRedirect(route('admin.product-categories.index'));
        $this->assertNotNull($child->fresh()->archived_at);
    }

    public function test_category_form_uses_accessible_bounded_fields_and_served_revision_marker(): void
    {
        $this->actingAs($this->manager)->get(route('admin.product-categories.create'))
            ->assertOk()
            ->assertSee('data-admin-ui-revision="ecom-home-2e"', false)
            ->assertSee('for="category-name"', false)
            ->assertSee('id="category-name"', false)
            ->assertSee('for="category-parent"', false)
            ->assertSee('id="category-description"', false)
            ->assertSeeText('Category image')
            ->assertSeeText('Choose media');
    }

    public function test_generic_product_editor_is_product_agnostic_and_uses_clear_media_controls(): void
    {
        $product = app(CreateProduct::class)->handle($this->manager, 'item-one', 'item-one');
        app(CreateProductRevision::class)->handle($this->manager, $product, 0, ['title' => 'Item One', 'features' => []]);
        $unselected = $this->image();

        $this->actingAs($this->manager)->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('for="product-title"', false)
            ->assertSee('id="product-slug"', false)
            ->assertSeeText('Used in this Product’s storefront URL.')
            ->assertDontSeeText('Oxford URL')
            ->assertDontSeeText('Gallery Gallery order')
            ->assertSeeText('Add images')
            ->assertSeeText('Complete the required Product information before viewing it on the storefront.')
            ->assertDontSee('/products/item-one', false)
            ->assertDontSee($unselected->id, false);
    }

    public function test_manager_creates_complete_colour_and_size_product_in_one_save(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Shirts', 'slug' => 'shirts', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $ivoryImage = $this->image();

        $response = $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => 'Physical Test Linen Shirt', 'slug' => 'physical-test-linen-shirt',
            'short_description' => 'A lightweight linen shirt.', 'description' => 'Cut for warm days.',
            'materials' => 'Linen', 'care' => 'Cool wash', 'fit' => 'Regular', 'features' => "Mother-of-pearl buttons\nSide vents",
            'currency' => 'TZS', 'base_price' => '185,000', 'compare_at_price' => '200,000',
            'primary_category_id' => $category->id,
            'draft_colours' => ['ivory' => ['name' => 'Ivory', 'swatch_hex' => '#F5F0E8', 'media_ids' => [$ivoryImage->id]], 'navy' => ['name' => 'Navy', 'swatch_hex' => '#16233B']],
            'draft_sizes' => ['s' => ['name' => 'S'], 'm' => ['name' => 'M'], 'l' => ['name' => 'L'], 'xl' => ['name' => 'XL']],
            'draft_variants' => $this->draftVariants(['ivory' => 'Ivory', 'navy' => 'Navy'], ['s' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL'], 'physical-test-linen-shirt'),
            'default_variant_key' => 'ivory--s', 'status' => 'hidden', 'gallery_media_ids' => [],
        ]);

        $product = Product::query()->where('slug', 'physical-test-linen-shirt')->sole();
        $response->assertRedirect(route('admin.products.edit', $product))->assertSessionHas('status', 'Product created successfully.');
        $this->assertSame(18500000, $product->base_price_minor);
        $this->assertSame(20000000, $product->compare_at_price_minor);
        $this->assertCount(8, $product->variants()->active()->get());
        $this->assertNotNull($product->fresh()->default_variant_id);
        $this->assertSame($category->id, $product->categories()->wherePivot('is_primary', true)->sole()->id);
        $ivory = $product->options()->where('key', 'colour')->firstOrFail()->values()->where('key', 'ivory')->sole();
        $this->assertDatabaseHas('media_usages', ['owner_type' => ProductOptionValue::class, 'owner_identifier' => $ivory->id, 'media_asset_id' => $ivoryImage->id, 'field_role' => 'colour_primary']);
        $this->actingAs($this->manager)->get(route('admin.products.index'))->assertOk()->assertSeeText('Physical Test Linen Shirt');
        $this->actingAs($this->manager)->get(route('admin.products.edit', $product))->assertOk()
            ->assertSeeText('8 sellable combinations')
            ->assertSeeText('2 Colours × 4 Sizes')
            ->assertSee('class="admin-table-wrap product-variant-table-wrap"', false)
            ->assertSee('data-label="SKU"', false)
            ->assertSee('data-label="Price"', false)
            ->assertDontSee('>ivory<', false)
            ->assertDontSee('>navy<', false);
    }

    public function test_blank_slug_is_generated_and_create_form_does_not_require_it_in_browser(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Shirts', 'slug' => 'shirts', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $this->actingAs($this->manager)->get(route('admin.products.create'))->assertOk()
            ->assertSee('id="product-slug" name="slug" value="" maxlength="160"', false)
            ->assertDontSee('id="product-slug" name="slug" value="" required', false);
        $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => 'Generated Slug Linen Shirt', 'slug' => '', 'currency' => 'TZS', 'base_price' => '85,000',
            'primary_category_id' => $category->id, 'draft_variants' => [['key' => 'none--none', 'label' => 'Default', 'sku' => 'WT-GENERATED-SLUG']],
            'default_variant_key' => 'none--none', 'status' => 'hidden', 'gallery_media_ids' => [],
        ])->assertRedirect();
        $this->assertDatabaseHas('products', ['slug' => 'generated-slug-linen-shirt']);
    }

    public function test_colour_editor_uses_synchronised_native_picker_and_explains_variant_matrix(): void
    {
        $this->actingAs($this->manager)->get(route('admin.products.create'))->assertOk()
            ->assertSee('type="color"', false)
            ->assertSee('#808080', false)
            ->assertSeeText("A Variant is one sellable combination of this Product's options")
            ->assertSeeText('Uses Product price')
            ->assertSee('data-label="Default"', false)
            ->assertSeeText('Inactive Variants cannot be selected for purchase');
    }

    public function test_invalid_swatch_returns_field_error_and_preserves_colour_draft(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Shirts', 'slug' => 'shirts', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $response = $this->actingAs($this->manager)->from(route('admin.products.create'))->post(route('admin.products.store'), [
            'title' => 'Invalid Swatch Shirt', 'slug' => '', 'currency' => 'TZS', 'base_price' => '90,000', 'status' => 'hidden',
            'primary_category_id' => $category->id, 'gallery_media_ids' => [],
            'draft_colours' => ['black-draft' => ['name' => 'Black', 'swatch_hex' => 'black']],
            'draft_variants' => [['key' => 'black-draft--none', 'colour_key' => 'black-draft', 'size_key' => '', 'label' => 'Black', 'sku' => 'WT-INVALID-SWATCH-BLACK']],
            'default_variant_key' => 'black-draft--none',
        ]);

        $response->assertRedirect(route('admin.products.create'))->assertSessionHasErrors('draft_colours.black-draft.swatch_hex');
        $this->actingAs($this->manager)->get(route('admin.products.create'))->assertOk()->assertSee('Invalid Swatch Shirt', false)->assertSee('black', false)->assertSee('black-draft', false);
        $this->assertDatabaseMissing('products', ['slug' => 'invalid-swatch-shirt']);
    }

    public function test_create_validation_preserves_product_and_colour_media_draft_state(): void
    {
        $image = $this->image();
        $response = $this->actingAs($this->manager)->from(route('admin.products.create'))->post(route('admin.products.store'), [
            'title' => 'Preserved Draft', 'slug' => '', 'currency' => 'TZS', 'base_price' => '90,000', 'status' => 'active',
            'primary_media_id' => $image->id, 'gallery_media_ids' => [$image->id], 'media_alt' => [$image->id => 'Preserved image'],
            'draft_colours' => ['black-draft' => ['name' => 'Black', 'swatch_hex' => '#000000', 'media_ids' => [$image->id]]],
            'draft_sizes' => ['medium-draft' => ['name' => 'M']],
            'draft_variants' => [['key' => 'black-draft--medium-draft', 'colour_key' => 'black-draft', 'size_key' => 'medium-draft', 'label' => 'Black / M', 'sku' => 'WT-PRESERVED-BLK-M']],
            'default_variant_key' => 'black-draft--medium-draft',
        ]);
        $response->assertRedirect(route('admin.products.create'))->assertSessionHasErrors('primary_category_id');
        $this->actingAs($this->manager)->get(route('admin.products.create'))->assertOk()
            ->assertSee('Preserved Draft', false)->assertSee('Black', false)->assertSee('black-draft--medium-draft', false)->assertSee($image->id, false);
        $this->assertDatabaseMissing('products', ['slug' => 'preserved-draft']);
    }

    public function test_creation_supports_size_only_and_no_option_products(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Accessories', 'slug' => 'accessories', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        foreach ([['size-only-belt', ['size-30' => '30', 'size-32' => '32', 'size-34' => '34'], 3], ['no-option-scarf', [], 1]] as [$slug, $sizes, $count]) {
            $variants = $this->draftVariants([], $sizes, $slug);
            $response = $this->actingAs($this->manager)->post(route('admin.products.store'), [
                'title' => Str::headline($slug), 'slug' => $slug, 'currency' => 'TZS', 'base_price' => '50,000',
                'primary_category_id' => $category->id, 'draft_sizes' => collect($sizes)->map(fn ($name) => ['name' => $name])->all(),
                'draft_variants' => $variants, 'default_variant_key' => $variants[0]['key'], 'status' => 'hidden', 'gallery_media_ids' => [],
            ])->assertRedirect();
            $response->assertSessionHasNoErrors();
            $product = Product::query()->where('slug', $slug)->sole();
            $this->assertCount($count, $product->variants()->active()->get());
            $this->assertNotNull($product->fresh()->default_variant_id);
        }
    }

    public function test_product_create_validation_preserves_input_and_does_not_create_partial_product(): void
    {
        $this->actingAs($this->manager)->from(route('admin.products.create'))->post(route('admin.products.store'), [
            'title' => 'Invalid Product', 'slug' => 'invalid-product', 'currency' => 'TZS', 'base_price' => '100,000', 'status' => 'hidden',
        ])->assertRedirect(route('admin.products.create'))->assertSessionHasErrors('primary_category_id')->assertSessionHasInput('title', 'Invalid Product');
        $this->assertDatabaseMissing('products', ['slug' => 'invalid-product']);
    }

    public function test_new_active_product_uses_canonical_dynamic_storefront_route(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Tailoring', 'slug' => 'tailoring', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $image = $this->image();
        $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => 'Generic Navy Overshirt', 'slug' => 'generic-navy-overshirt', 'short_description' => 'Layered tailoring.',
            'currency' => 'TZS', 'base_price' => '225,000', 'primary_category_id' => $category->id,
            'primary_media_id' => $image->id, 'media_alt' => [$image->id => 'Generic navy overshirt'],
            'gallery_media_ids' => [], 'draft_variants' => [['key' => 'none--none', 'colour_key' => '', 'size_key' => '', 'label' => 'Default', 'sku' => 'WT-GENERIC-NAVY-OVERSHIRT', 'price' => '']],
            'default_variant_key' => 'none--none', 'status' => 'active',
        ])->assertRedirect();

        $this->get('/products/generic-navy-overshirt')->assertOk()
            ->assertSeeText('Generic Navy Overshirt')
            ->assertSeeText('TZS 225,000')
            ->assertSee('id="product-bootstrap-data"', false)
            ->assertSee('"slug":"generic-navy-overshirt"', false)
            ->assertDontSee('/website/js/index-DxdnTNDA.js', false);
        $product = Product::query()->where('slug', 'generic-navy-overshirt')->sole();
        $this->actingAs($this->manager)->get(route('admin.products.edit', $product))->assertOk()
            ->assertSeeText('Ready for storefront')->assertSee('/products/generic-navy-overshirt', false);
        $this->actingAs($this->manager)->get(route('admin.products.index'))->assertOk()
            ->assertSeeText('Generic Navy Overshirt')
            ->assertSeeText('View storefront')
            ->assertSee('catalogue-status-badge is-active', false);
    }

    public function test_archived_generic_product_is_not_publicly_resolvable(): void
    {
        $product = app(CreateProduct::class)->handle($this->manager, 'archived-generic', 'archived-generic');
        $product->forceFill(['catalogue_status' => 'ready', 'archived_at' => now()])->save();

        $this->get('/products/archived-generic')->assertNotFound();
    }

    public function test_category_media_picker_selection_persists_and_removal_preserves_asset(): void
    {
        $image = $this->image();
        $this->actingAs($this->manager)->post(route('admin.product-categories.store'), [
            'name' => 'Editorial', 'slug' => 'editorial', 'description' => 'Editorial pieces',
            'image_media_asset_id' => $image->id, 'is_visible' => '1', 'position' => 0,
        ])->assertRedirect();

        $category = ProductCategory::query()->where('slug', 'editorial')->sole();
        $this->assertSame($image->id, $category->image_media_asset_id);
        $this->actingAs($this->manager)->get(route('admin.product-categories.edit', $category))
            ->assertOk()->assertSee($image->id, false)->assertSeeText('Remove');

        $this->actingAs($this->manager)->put(route('admin.product-categories.update', $category), [
            'name' => 'Editorial', 'slug' => 'editorial', 'description' => 'Editorial pieces',
            'image_media_asset_id' => '', 'is_visible' => '1', 'position' => 0,
        ])->assertRedirect();
        $this->assertNull($category->fresh()->image_media_asset_id);
        $this->assertTrue(MediaAsset::query()->whereKey($image->id)->exists());
    }

    public function test_integer_minor_pricing_formats_and_variant_override_inherits(): void
    {
        $prices = app(ProductPrice::class);
        $product = new Product(['base_price_minor' => 28500000, 'currency' => 'TZS']);
        $this->assertSame(28500000, $prices->parse('285,000'));
        $this->assertSame('TZS 285,000', $prices->format($product->base_price_minor, $product->currency));
        $this->assertSame(28500000, $prices->effectiveMinor($product));
        $this->assertSame(29500000, $prices->effectiveMinor($product, new ProductVariant(['price_override_minor' => 29500000])));
    }

    public function test_hidden_canonical_product_fails_closed_instead_of_using_static_fallback(): void
    {
        $product = app(CreateProduct::class)->handle($this->manager, 'mercerized-cotton-polo', 'mercerized-cotton-polo');
        app(CreateProductRevision::class)->handle($this->manager, $product, 0, ['title' => 'Hidden Polo', 'features' => []]);

        $this->get('/products/mercerized-cotton-polo')->assertNotFound();
        $this->get('/products/unregistered-product')->assertNotFound();
    }

    public function test_product_can_have_primary_and_additional_categories(): void
    {
        $parent = ProductCategory::query()->create(['name' => 'Men', 'slug' => 'men', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $shirts = ProductCategory::query()->create(['name' => 'Shirts', 'slug' => 'shirts', 'parent_id' => $parent->id, 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $product = app(CreateProduct::class)->handle($this->manager, 'classic-shirt', 'classic-shirt');
        $product->categories()->sync([$shirts->id => ['is_primary' => true, 'position' => 0], $parent->id => ['is_primary' => false, 'position' => 1]]);

        $this->assertSame('Shirts', $product->categories()->wherePivot('is_primary', true)->sole()->name);
        $this->assertCount(2, $product->categories);
    }

    public function test_colour_gallery_is_owned_by_colour_and_can_be_removed_without_deleting_asset(): void
    {
        $this->artisan('catalogue:bootstrap-oxford', ['--user' => $this->manager->email])->assertSuccessful();
        $product = Product::query()->where('slug', 'the-taylor-oxford-shirt')->with('options.values')->sole();
        $colour = $product->options->firstWhere('key', 'colour')->values->first();
        $asset = $this->image();

        $payload = $this->updatePayload($product);
        $payload['colour_media'] = [$colour->id => [$asset->id]];
        $this->actingAs($this->manager)->put(route('admin.products.update', $product), $payload)->assertRedirect();
        $this->assertDatabaseHas('media_usages', ['owner_type' => $colour::class, 'owner_identifier' => $colour->id, 'media_asset_id' => $asset->id, 'field_role' => 'colour_primary']);

        $product->refresh();
        $payload = $this->updatePayload($product);
        $payload['colour_media'] = [$colour->id => []];
        $this->actingAs($this->manager)->put(route('admin.products.update', $product), $payload)->assertRedirect();
        $this->assertFalse(MediaUsage::query()->where('owner_type', $colour::class)->where('owner_identifier', $colour->id)->exists());
        $this->assertTrue(MediaAsset::query()->whereKey($asset->id)->exists());
    }

    public function test_dynamic_product_uses_default_colour_gallery_and_exposes_safe_colour_fallback(): void
    {
        $category = ProductCategory::query()->create(['name' => 'Gallery', 'slug' => 'gallery', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $primary = $this->image();
        $blackFirst = $this->image();
        $blackSecond = $this->image();
        $white = $this->image();
        $variants = $this->draftVariants(['black' => 'Black', 'white' => 'White', 'beige' => 'Beige'], ['s' => 'S', 'm' => 'M'], 'gallery-shirt');

        $this->actingAs($this->manager)->post(route('admin.products.store'), [
            'title' => 'Gallery Shirt', 'slug' => 'gallery-shirt', 'short_description' => 'Canonical gallery Product.',
            'currency' => 'TZS', 'base_price' => '320,000', 'primary_category_id' => $category->id,
            'primary_media_id' => $primary->id, 'media_alt' => [$primary->id => 'Gallery Shirt'], 'gallery_media_ids' => [],
            'draft_colours' => [
                'black' => ['name' => 'Black', 'swatch_hex' => '#000000', 'media_ids' => [$blackFirst->id, $blackSecond->id], 'media_order' => [$blackFirst->id => 0, $blackSecond->id => 1]],
                'white' => ['name' => 'White', 'swatch_hex' => '#FFFFFF', 'media_ids' => [$white->id], 'media_order' => [$white->id => 0]],
                'beige' => ['name' => 'Beige', 'swatch_hex' => '#D8C3A5', 'media_ids' => []],
            ],
            'draft_sizes' => ['s' => ['name' => 'S'], 'm' => ['name' => 'M']],
            'draft_variants' => $variants, 'default_variant_key' => 'black--s', 'status' => 'active',
        ])->assertRedirect();

        $product = Product::query()->where('slug', 'gallery-shirt')->sole();
        $presented = app(ProductPresenter::class)->resolve($product->slug);
        $black = collect($presented['options']['colour'])->firstWhere('key', 'black');
        $whiteOption = collect($presented['options']['colour'])->firstWhere('key', 'white');
        $beige = collect($presented['options']['colour'])->firstWhere('key', 'beige');
        $this->assertCount(2, $presented['colour_images'][$black['id']]);
        $this->assertCount(1, $presented['colour_images'][$whiteOption['id']]);
        $this->assertSame([], $presented['colour_images'][$beige['id']]);

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('src="'.$presented['colour_images'][$black['id']][0]['url'].'"', false)
            ->assertSee('const fallbackImages = productData?.images || [];', false)
            ->assertSee('renderColourPreview(productData.colour_images[button.dataset.valueId])', false)
            ->assertDontSee('gallery.replaceChildren', false)
            ->assertSeeText('SKU:')->assertSeeText('TZS 320,000')
            ->assertSeeText('Free delivery in Dar es Salaam. 2–4 days nationwide.')
            ->assertDontSee('Ã', false);
    }

    /** @return array<string, mixed> */
    private function updatePayload(Product $product): array
    {
        return [
            'lock_version' => $product->lock_version,
            'title' => $product->currentDraftRevision->title,
            'slug' => $product->slug,
            'short_description' => $product->currentDraftRevision->short_description,
            'description' => '',
            'materials' => $product->currentDraftRevision->materials,
            'fit' => $product->currentDraftRevision->fit,
            'care' => $product->currentDraftRevision->care,
            'features' => implode("\n", $product->currentDraftRevision->features ?? []),
            'status' => 'hidden',
            'gallery_media_ids' => [],
            'option_labels' => [],
            'variant_skus' => [],
        ];
    }

    /** @param array<string, string> $colours @param array<string, string> $sizes @return list<array<string, string>> */
    private function draftVariants(array $colours, array $sizes, string $slug): array
    {
        $pairs = $colours !== [] && $sizes !== []
            ? collect(array_keys($colours))->crossJoin(array_keys($sizes))->map(fn ($pair) => [$pair[0], $pair[1]])
            : ($colours !== [] ? collect(array_keys($colours))->map(fn ($colour) => [$colour, '']) : ($sizes !== [] ? collect(array_keys($sizes))->map(fn ($size) => ['', $size]) : collect([['', '']])));

        return $pairs->map(function ($pair) use ($colours, $sizes, $slug): array {
            [$colour, $size] = $pair;
            $label = implode(' / ', array_filter([$colours[$colour] ?? '', $sizes[$size] ?? ''])) ?: 'Default';

            return ['key' => ($colour ?: 'none').'--'.($size ?: 'none'), 'colour_key' => $colour, 'size_key' => $size, 'label' => $label, 'sku' => strtoupper('WT-'.$slug.'-'.$colour.'-'.$size), 'price' => ''];
        })->values()->all();
    }

    private function image(): MediaAsset
    {
        $id = (string) Str::ulid();

        return MediaAsset::query()->create([
            'id' => $id, 'provider_asset_id' => 'asset-'.$id, 'provider_public_id' => 'catalogue/'.$id,
            'resource_type' => MediaResourceType::Image, 'format' => 'jpg', 'mime_type' => 'image/jpeg',
            'original_filename' => 'colour.jpg', 'internal_title' => 'Colour image', 'default_alt_text' => 'Colour image',
            'accessibility_classification' => AccessibilityClassification::Informative, 'is_decorative' => false,
            'state' => MediaAssetState::Ready, 'bytes' => 1000, 'uploaded_by' => $this->manager->id, 'confirmed_at' => now(),
        ]);
    }
}
