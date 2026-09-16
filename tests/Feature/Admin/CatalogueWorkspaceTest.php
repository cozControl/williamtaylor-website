<?php

namespace Tests\Feature\Admin;

use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Models\ProductVariant;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CategoryOwner;
use Tests\TestCase;

final class CatalogueWorkspaceTest extends TestCase
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

    public function test_catalogue_workspace_requires_authentication_and_product_view_permission(): void
    {
        $this->get(route('admin.catalogue.index'))->assertRedirect(route('login'));
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($ordinary)->get(route('admin.catalogue.index'))->assertForbidden();
        $this->actingAs($this->manager)->get(route('admin.catalogue.index'))->assertOk()
            ->assertSeeText('Manage Products, Categories, Collections and sellable Variants.')
            ->assertSee('data-admin-ui-revision="ecom-home-2e"', false)
            ->assertSee('data-admin-form-styles="ecom-home-2e"', false)
            ->assertSee('admin-page-heading-actions', false)
            ->assertSee('grid-template-columns:repeat(3,minmax(0,1fr))!important', false)
            ->assertSeeText('Catalogue management')
            ->assertSeeTextInOrder(['Manage Products', 'Manage Categories', 'Manage Collections'])
            ->assertSee(route('admin.products.index'), false)
            ->assertSee(route('admin.product-categories.index'), false);

        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $viewer->givePermissionTo(['admin.access', 'products.view']);
        $this->actingAs($viewer)->get(route('admin.catalogue.index'))->assertOk()
            ->assertDontSee('New Product')->assertSeeText('Manage Products')->assertSeeText('Manage Categories');
    }

    public function test_catalogue_workspace_uses_canonical_counts_and_real_attention_rules(): void
    {
        $category = ProductCategory::query()->create(['collection_id' => CategoryOwner::for($this->manager->id)->id, 'name' => 'Shirts', 'slug' => 'shirts', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $complete = Product::query()->create(['stable_key' => 'complete', 'slug' => 'complete', 'product_type' => 'apparel', 'catalogue_status' => 'ready', 'base_price_minor' => 10000, 'currency' => 'TZS', 'created_by' => $this->manager->id]);
        $complete->categories()->attach($category, ['is_primary' => true, 'position' => 0]);
        ProductVariant::query()->create(['id' => (string) Str::ulid(), 'product_id' => $complete->id, 'sku' => 'COMPLETE-1', 'combination_fingerprint' => hash('sha256', 'complete'), 'position' => 0, 'created_by' => $this->manager->id]);
        Product::query()->create(['stable_key' => 'attention', 'slug' => 'attention', 'product_type' => 'apparel', 'catalogue_status' => 'draft', 'currency' => 'TZS', 'created_by' => $this->manager->id]);

        $response = $this->actingAs($this->manager)->get(route('admin.catalogue.index'))->assertOk();
        $this->assertSame(3, substr_count($response->getContent(), 'class="catalogue-shortcut"'));
        $this->assertSame(9, substr_count($response->getContent(), '<article'));
        $response->assertViewHas('metrics', fn (array $metrics): bool => $metrics['products'] === 2
            && $metrics['active_products'] === 1
            && $metrics['categories'] === 1
            && $metrics['variants'] === 1
            && $metrics['needs_attention'] === 2);
    }

    public function test_navigation_and_product_index_render_commerce_first_responsive_structure(): void
    {
        $catalogue = $this->actingAs($this->manager)->get(route('admin.catalogue.index'))->assertOk();
        $catalogue->assertSeeInOrder(['Overview', 'Catalogue', 'Products', 'Categories', 'Website']);
        $catalogue->assertSee('href="'.route('admin.catalogue.index').'"', false)
            ->assertSeeTextInOrder(['Catalogue', 'Products', 'Categories', 'Collections']);

        $this->actingAs($this->manager)->get(route('admin.products.index'))
            ->assertOk()->assertSee('aria-label="Product filters"', false)
            ->assertSee('admin-page-heading-actions', false)
            ->assertSeeInOrder(['Manage products, pricing, imagery and variants.', 'New Product', 'Filters'])
            ->assertSeeText('Search products')->assertSeeText('All categories')
            ->assertSeeTextInOrder(['Clear', 'Apply filters'])
            ->assertSee('catalogue-filter-grid', false)
            ->assertSee('catalogue-product-table', false)
            ->assertSee('product-column', false)
            ->assertSee('catalogue-pagination', false)
            ->assertSee('data-group-active="true"', false);
    }

    public function test_product_index_filters_by_search_and_category(): void
    {
        $category = ProductCategory::query()->create(['collection_id' => CategoryOwner::for($this->manager->id)->id, 'name' => 'Shirts', 'slug' => 'shirts', 'is_visible' => true, 'position' => 0, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $shirt = Product::query()->create(['stable_key' => 'linen-shirt', 'slug' => 'linen-shirt', 'product_type' => 'apparel', 'catalogue_status' => 'draft', 'currency' => 'TZS', 'created_by' => $this->manager->id]);
        $shirt->categories()->attach($category, ['is_primary' => true, 'position' => 0]);
        Product::query()->create(['stable_key' => 'wool-coat', 'slug' => 'wool-coat', 'product_type' => 'apparel', 'catalogue_status' => 'draft', 'currency' => 'TZS', 'created_by' => $this->manager->id]);

        $this->actingAs($this->manager)->get(route('admin.products.index', ['search' => 'linen']))
            ->assertOk()->assertSeeText('/linen-shirt')->assertDontSeeText('/wool-coat');
        $this->actingAs($this->manager)->get(route('admin.products.index', ['category' => $category->id]))
            ->assertOk()->assertSeeText('/linen-shirt')->assertDontSeeText('/wool-coat');
        $this->actingAs($this->manager)->get(route('admin.products.index', ['status' => 'hidden']))
            ->assertOk()->assertSeeText('/linen-shirt')->assertSeeText('/wool-coat');
        $this->actingAs($this->manager)->get(route('admin.products.index', ['status' => 'active']))
            ->assertOk()->assertDontSeeText('/linen-shirt')->assertDontSeeText('/wool-coat');
    }

    public function test_catalogue_create_actions_use_the_shared_page_heading_region(): void
    {
        foreach ([
            route('admin.products.index') => 'New Product',
            route('admin.collections.index') => 'New Collection',
            route('admin.product-categories.index') => 'New Category',
        ] as $url => $label) {
            $this->actingAs($this->manager)->get($url)->assertOk()
                ->assertSee('admin-page-heading-actions', false)
                ->assertSeeText($label);
        }
    }
}
