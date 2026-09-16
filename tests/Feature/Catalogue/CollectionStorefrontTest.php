<?php

namespace Tests\Feature\Catalogue;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Checkout\Models\Order;
use App\Domain\Checkout\Models\OrderLine;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CollectionCatalogueFixture;
use Tests\TestCase;

final class CollectionStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private CollectionCatalogueFixture $fixture;

    private Collection $formal;

    private Collection $casual;

    private ProductCategory $shirts;

    private ProductCategory $polos;

    private Product $shirt;

    private Product $polo;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $actor = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $actor->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->fixture = new CollectionCatalogueFixture($actor);
        $this->formal = $this->fixture->collection('formal-wear');
        $this->casual = $this->fixture->collection('casual-wear');
        $this->shirts = $this->fixture->category($this->formal, 'shirts');
        $this->polos = $this->fixture->category($this->casual, 'polos');
        $this->shirt = $this->fixture->product($this->formal, $this->shirts, 'formal-shirt', 'M');
        $this->polo = $this->fixture->product($this->casual, $this->polos, 'casual-polo', 'XXL');
    }

    public function test_canonical_route_and_public_heading(): void
    {
        $this->assertSame('/collections/formal-wear', route('collections.show', $this->formal->slug, false));
        $this->get($this->url())->assertOk()->assertSeeText('Formal Wear')->assertSeeText('Pieces selected for formal-wear.');
    }

    public function test_unpublished_archived_and_missing_collections_are_not_public(): void
    {
        $this->formal->update(['catalogue_status' => 'draft']);
        $this->get($this->url())->assertNotFound();
        $this->formal->update(['catalogue_status' => 'ready', 'archived_at' => now()]);
        $this->get($this->url())->assertNotFound();
        $this->get('/collections/absent')->assertNotFound();
    }

    public function test_collection_cards_use_canonical_route(): void
    {
        $this->get(route('collections.index'))->assertOk()->assertSee('href="'.$this->url().'"', false);
    }

    public function test_shop_and_collection_share_catalogue_ui_and_canonical_card(): void
    {
        foreach ([$this->url(), route('products.index')] as $url) {
            $this->get($url)->assertOk()->assertSee('data-catalogue-toolbar', false)->assertSee('data-catalogue-grid', false)->assertSee('data-storefront-product-card', false)->assertSee('catalogue-listing.js')->assertDontSee('/website/js/index-DxdnTNDA.js');
        }
    }

    public function test_membership_is_authoritative_and_category_does_not_add_products(): void
    {
        $this->polo->categories()->attach($this->shirts->id, ['is_primary' => false]);
        $this->get($this->url())->assertSeeText('Formal Shirt')->assertDontSeeText('Casual Polo');
        $this->assertDatabaseCount('collection_products', 2);
    }

    public function test_category_relationship_and_database_ownership(): void
    {
        $this->assertTrue($this->shirts->collection->is($this->formal));
        $this->assertTrue($this->formal->categories->contains($this->shirts));
        $this->expectException(QueryException::class);
        $this->shirts->update(['collection_id' => null]);
    }

    public function test_category_create_requires_collection_and_retains_authorization(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))->post(route('admin.product-categories.store'), $this->payload())->assertForbidden();
        $this->actingAs($this->fixture->actor)->post(route('admin.product-categories.store'), $this->payload())->assertSessionHasErrors('collection_id');
        $this->assertDatabaseCount('product_categories', 2);
    }

    public function test_category_create_and_update_require_valid_owned_slug(): void
    {
        $this->actingAs($this->fixture->actor)->post(route('admin.product-categories.store'), [...$this->payload(), 'collection_id' => $this->formal->id])->assertSessionHasNoErrors();
        $category = ProductCategory::where('slug', 'new-category')->sole();
        $this->actingAs($this->fixture->actor)->put(route('admin.product-categories.update', $category), [...$this->payload(), 'collection_id' => $this->formal->id, 'name' => 'Updated'])->assertSessionHasNoErrors();
        $this->assertSame($this->formal->id, $category->fresh()->collection_id);
    }

    public function test_admin_register_and_collection_filter(): void
    {
        $this->actingAs($this->fixture->actor)->get(route('admin.product-categories.index', ['collection' => $this->formal->id]))->assertOk()->assertSeeText('Formal Wear')->assertSeeText('Shirts')->assertDontSeeText('Polos');
        $this->get(route('admin.product-categories.edit', $this->shirts))->assertOk()->assertSee('name="collection_id" required', false);
    }

    public function test_slug_uniqueness_is_collection_scoped(): void
    {
        $this->actingAs($this->fixture->actor)->post(route('admin.product-categories.store'), [...$this->payload(), 'slug' => 'shirts', 'collection_id' => $this->casual->id])->assertSessionHasNoErrors();
        $this->post(route('admin.product-categories.store'), [...$this->payload(), 'slug' => 'shirts', 'collection_id' => $this->formal->id])->assertSessionHasErrors('slug');
        $this->assertSame(2, ProductCategory::where('slug', 'shirts')->count());
    }

    public function test_category_move_requires_membership_compatibility(): void
    {
        $payload = [...$this->payload(), 'name' => 'Shirts', 'slug' => 'shirts', 'collection_id' => $this->casual->id];
        $this->actingAs($this->fixture->actor)->put(route('admin.product-categories.update', $this->shirts), $payload)->assertSessionHasErrors('collection_id');
        $this->assertSame($this->formal->id, $this->shirts->fresh()->collection_id);
        $this->fixture->assign($this->casual, $this->shirt);
        $before = DB::table('collection_products')->orderBy('id')->get()->toJson();
        $this->put(route('admin.product-categories.update', $this->shirts), $payload)->assertSessionHasNoErrors();
        $this->assertSame($before, DB::table('collection_products')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('product_category_assignments', ['product_id' => $this->shirt->id, 'product_category_id' => $this->shirts->id, 'is_primary' => true]);
    }

    public function test_parent_cannot_cross_collection_and_archive_preserves_assignments(): void
    {
        $this->actingAs($this->fixture->actor)->post(route('admin.product-categories.store'), [...$this->payload(), 'collection_id' => $this->casual->id, 'parent_id' => $this->shirts->id])->assertSessionHasErrors('parent_id');
        $this->patch(route('admin.product-categories.archive', $this->shirts))->assertRedirect();
        $this->assertDatabaseCount('product_category_assignments', 2);
    }

    public function test_category_and_size_facets_are_scoped(): void
    {
        $this->get($this->url())->assertOk()->assertViewHas('categories', fn ($c) => $c->pluck('id')->all() === [$this->shirts->id])->assertViewHas('sizes', fn ($s) => $s->pluck('key')->all() === ['m']);
    }

    public function test_foreign_or_unknown_category_filter_cannot_leak_products(): void
    {
        foreach (['polos', $this->polos->id, 'unknown'] as $category) {
            $this->get($this->url(['category' => $category]))->assertOk()->assertDontSeeText('Casual Polo')->assertViewHas('products', fn ($p) => $p->total() === 1);
        }
    }

    public function test_category_filter_further_narrows_membership(): void
    {
        $suits = $this->fixture->category($this->formal, 'suits');
        $this->fixture->product($this->formal, $suits, 'formal-suit');
        $this->get($this->url(['category' => 'shirts']))->assertOk()->assertSeeText('Formal Shirt')->assertDontSeeText('Formal Suit')->assertViewHas('products', fn ($p) => $p->total() === 1);
    }

    public function test_size_filter_and_all_existing_sort_choices(): void
    {
        $large = $this->fixture->product($this->formal, $this->shirts, 'large-shirt', 'L', 20000000);
        $this->get($this->url(['size' => 'l']))->assertOk()->assertViewHas('products', fn ($p) => $p->pluck('id')->all() === [$large->id]);
        foreach (['featured', 'newest', 'price-asc', 'price-desc', 'bestselling'] as $sort) {
            $this->get($this->url(['sort' => $sort]))->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 2);
        }
        $this->get($this->url(['sort' => 'price-desc']))->assertViewHas('products', fn ($p) => $p->first()->id === $large->id);
    }

    public function test_pagination_retains_context_filters_and_sort(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->fixture->product($this->formal, $this->shirts, 'shirt-'.$i, 'M');
        }
        $this->get($this->url(['category' => 'shirts', 'size' => ['m'], 'sort' => 'price-asc', 'page' => 2]))->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 13 && $p->count() === 1 && str_contains($p->previousPageUrl(), '/collections/formal-wear?') && str_contains($p->previousPageUrl(), 'category=shirts'))->assertSee('data-catalogue-pagination', false);
    }

    public function test_unpublished_and_unready_products_remain_hidden(): void
    {
        foreach ([['catalogue_status' => 'draft'], ['catalogue_status' => 'ready', 'base_price_minor' => null], ['base_price_minor' => 12500000, 'default_variant_id' => null]] as $change) {
            $this->shirt->update($change);
            $this->get($this->url())->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 0);
        }
    }

    public function test_unusable_media_is_hidden_even_when_stored_status_is_ready(): void
    {
        $this->shirt->mediaUsages->first()->asset->update(['state' => 'archived', 'archived_at' => now()]);
        $this->assertFalse(app(CatalogueReadinessEvaluator::class)->evaluate($this->shirt->fresh())->ready);
        $this->get($this->url())->assertOk()->assertViewHas('products', fn ($p) => $p->total() === 0);
    }

    public function test_stock_badge_uses_canonical_variant_ledger_availability(): void
    {
        $variant = $this->shirt->defaultVariant;
        $this->assertSame(0, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->get($this->url())->assertSee('data-inventory-out-of-stock', false);
        app(InventoryLedgerService::class)->post($this->fixture->actor, $variant, StockLocation::main(), MovementType::Receipt, 3, 'Catalogue test');
        $this->assertSame(3, app(InventoryAvailabilityService::class)->availableToSell($variant));
        $this->get($this->url())->assertDontSee('data-inventory-out-of-stock', false);
    }

    public function test_listing_queries_do_not_grow_per_product(): void
    {
        $measure = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($this->url())->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };
        $before = $measure();
        for ($i = 0; $i < 7; $i++) {
            $this->fixture->product($this->formal, $this->shirts, 'extra-'.$i, 'M');
        }
        $after = $measure();
        $this->assertLessThanOrEqual($before + 2, $after, "Query count grew from {$before} to {$after}.");
    }

    public function test_shop_remains_global_and_query_collection_redirects(): void
    {
        $this->get(route('products.index'))->assertOk()->assertSeeText('Formal Shirt')->assertSeeText('Casual Polo');
        $this->get(route('products.index', ['collection' => 'formal-wear', 'size' => 'm']))->assertStatus(301)->assertRedirect($this->url(['size' => 'm']));
    }

    public function test_metadata_uses_collection_canonical_and_filtered_robots(): void
    {
        $this->get($this->url(['category' => 'shirts']))->assertOk()->assertSee('Formal Wear | William Taylor')->assertSee('rel="canonical" href="'.$this->url().'"', false)->assertSee('content="noindex,follow"', false);
    }

    public function test_wishlist_resolves_only_ready_canonical_saved_products(): void
    {
        $this->shirt->update(['catalogue_status' => 'draft']);
        $this->get(route('wishlist.index', ['fragment' => 1, 'items' => [$this->shirt->slug, $this->polo->slug, 'missing']]))->assertOk()->assertDontSeeText('Formal Shirt')->assertSeeText('Casual Polo')->assertSee('data-count="1"', false);
        $this->assertDatabaseCount('product_category_assignments', 2);
    }

    public function test_bestselling_counts_paid_canonical_sales_and_keeps_collection_scope(): void
    {
        $second = $this->fixture->product($this->formal, $this->shirts, 'second-shirt');
        foreach ([[$this->shirt, 50, 'unpaid'], [$second, 2, 'paid'], [$this->polo, 100, 'paid']] as $i => [$product, $quantity, $status]) {
            $id = (string) Str::ulid();
            $order = Order::create(['id' => $id, 'order_number' => 'SORT-'.$i, 'confirmation_reference' => hash('sha256', $id), 'submission_key' => hash('sha256', 'submit'.$id), 'request_fingerprint' => hash('sha256', 'request'.$id), 'cart_fingerprint' => hash('sha256', 'cart'.$id), 'customer_snapshot' => [], 'delivery_snapshot' => [], 'currency' => 'TZS', 'subtotal_minor' => 100, 'total_minor' => 100, 'status' => $status === 'paid' ? 'confirmed' : 'pending_confirmation', 'payment_status' => $status, 'placed_at' => now()]);
            OrderLine::create(['order_id' => $order->id, 'product_id' => $product->id, 'variant_id' => $product->default_variant_id, 'product_title_snapshot' => $product->slug, 'sku_snapshot' => $product->defaultVariant->sku, 'options_snapshot' => [], 'unit_price_minor' => 100, 'quantity' => $quantity, 'line_total_minor' => 100 * $quantity]);
        }
        $this->get($this->url(['sort' => 'bestselling']))->assertOk()->assertViewHas('products', fn ($p) => $p->pluck('id')->all() === [$second->id, $this->shirt->id]);
    }

    public function test_public_query_matches_canonical_readiness_for_invalid_structures(): void
    {
        $mutations = [
            fn ($p) => $p->update(['product_type' => 'unsupported']),
            fn ($p) => $p->update(['current_draft_revision_id' => $this->polo->current_draft_revision_id]),
            fn ($p) => $p->categories()->detach(),
            fn ($p) => $p->defaultVariant->update(['sku' => '']),
            fn ($p) => $p->defaultVariant->update(['archived_at' => now()]),
            fn ($p) => $p->variants()->first()->values()->detach(),
            fn ($p) => $p->mediaUsages()->first()->update(['decorative_override' => true]),
            fn ($p) => $p->mediaUsages()->first()->update(['alt_text_override' => '<b>Invalid alt</b>']),
            fn ($p) => $p->mediaUsages()->first()->update(['alt_text_override' => '']),
            fn ($p) => $p->mediaUsages()->first()->delete(),
        ];
        foreach ($mutations as $i => $mutate) {
            $product = $this->fixture->product($this->formal, $this->shirts, 'invalid-'.$i, 'M');
            $mutate($product);
            $readiness = app(CatalogueReadinessEvaluator::class);
            $this->assertFalse($readiness->evaluate($product->fresh())->ready, 'Fixture must be unready '.$i);
            $this->assertFalse($readiness->publicQuery()->whereKey($product->id)->exists(), 'Public SQL must preserve readiness '.$i);
        }
    }

    public function test_migration_fails_before_schema_changes_for_unknown_ambiguous_owners(): void
    {
        $this->migrationDatabase(function (): void {
            DB::table('collection_products')->insert(['collection_id' => 'casual', 'product_id' => 'shirt', 'archived_at' => null]);
            $migration = require database_path('migrations/2026_09_16_180000_scope_product_categories_to_collections.php');
            try {
                $migration->up();
                $this->fail('Ambiguous historical category was guessed.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('legacy', $e->getMessage());
            }
            $this->assertFalse(Schema::hasColumn('product_categories', 'collection_id'));
            $this->assertSame(1, DB::table('product_category_assignments')->count());
        });
    }

    public function test_migration_infers_only_unambiguous_owner_and_preserves_pivots(): void
    {
        $this->migrationDatabase(function (): void {
            $before = DB::table('product_category_assignments')->get()->toJson();
            $migration = require database_path('migrations/2026_09_16_180000_scope_product_categories_to_collections.php');
            $migration->up();
            $this->assertSame('formal', DB::table('product_categories')->where('id', 'legacy')->value('collection_id'));
            $this->assertSame($before, DB::table('product_category_assignments')->get()->toJson());
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        });
    }

    private function migrationDatabase(\Closure $check): void
    {
        $original = DB::getDefaultConnection();
        config(['database.connections.ownership_test' => [...config('database.connections.sqlite'), 'database' => ':memory:']]);
        DB::setDefaultConnection('ownership_test');
        try {
            Schema::create('collections', function ($t): void {
                $t->ulid('id')->primary();
            });
            Schema::create('product_categories', function ($t): void {
                $t->ulid('id')->primary();
                $t->string('slug')->unique('product_categories_slug_unique');
                $t->ulid('parent_id')->nullable();
                $t->timestamp('archived_at')->nullable();
                $t->boolean('is_visible')->default(true);
                $t->integer('position')->default(0);
            });
            Schema::create('product_category_assignments', function ($t): void {
                $t->ulid('product_id');
                $t->foreignUlid('product_category_id')->constrained('product_categories')->restrictOnDelete();
            });
            Schema::create('collection_products', function ($t): void {
                $t->ulid('collection_id');
                $t->ulid('product_id');
                $t->timestamp('archived_at')->nullable();
            });
            DB::table('collections')->insert([['id' => 'formal'], ['id' => 'casual']]);
            DB::table('product_categories')->insert(['id' => 'legacy', 'slug' => 'legacy']);
            DB::table('product_category_assignments')->insert(['product_id' => 'shirt', 'product_category_id' => 'legacy']);
            DB::table('collection_products')->insert(['collection_id' => 'formal', 'product_id' => 'shirt', 'archived_at' => null]);
            $check();
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('ownership_test');
        }
    }

    private function url(array $query = []): string
    {
        return route('collections.show', ['collection' => $this->formal->slug, ...$query]);
    }

    private function payload(): array
    {
        return ['name' => 'New Category', 'slug' => 'new-category', 'is_visible' => true, 'position' => 0];
    }
}
