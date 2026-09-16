<?php

namespace Tests\Feature\Inventory;

use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CategoryOwner;
use Tests\TestCase;

final class StorefrontAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->manager = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $this->manager->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->category = ProductCategory::query()->create(['collection_id' => CategoryOwner::for($this->manager->id)->id,
            'name' => 'Explore',
            'slug' => 'explore',
            'is_visible' => true,
            'position' => 0,
            'created_by' => $this->manager->id,
            'updated_by' => $this->manager->id,
        ]);
    }

    public function test_stock_changes_propagate_to_detail_and_collection_without_hiding_product(): void
    {
        $product = $this->product('Stock Product', 'stock-product', true);
        $collection = $this->collection('Stock Collection', [$product], true);
        $variant = $product->defaultVariant;
        $ledger = app(InventoryLedgerService::class);
        $location = StockLocation::main();
        foreach ([0, 3, 0] as $step => $quantity) {
            if ($step === 1) {
                $ledger->post($this->manager, $variant, $location, MovementType::Opening, 3, 'Test opening');
            }
            if ($step === 2) {
                $ledger->count($this->manager, $variant, $location, 0, 3, 'Test recount');
            }
            $response = $this->get(route('products.show', $product))->assertOk();
            $data = $response->viewData('catalogueProduct');
            $this->assertSame($quantity > 0, $data['is_available']);
            $this->assertSame($quantity > 0, $data['variants'][0]['is_available']);
            $this->assertArrayNotHasKey('available_to_sell', $data['variants'][0]);
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xp = new \DOMXPath($dom);
            $button = $xp->query('//*[@data-product-purchase]')->item(0);
            $this->assertSame($quantity === 0, $button->hasAttribute('disabled'));
            $this->assertStringNotContainsString('index-DxdnTNDA.js', $response->getContent());
            $cards = $this->get(route('collections.show', $collection))->assertOk()->assertSee(route('products.show', $product), false)->viewData('productCards');
            $this->assertCount(1, $cards);
            $this->assertSame($quantity > 0, $cards[0]['is_available']);
            $this->assertSame($quantity, app(InventoryAvailabilityService::class)->availableToSell($variant));
        }
        $this->assertSame('ready', $product->fresh()->catalogue_status);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_bulk_availability_is_variant_specific_and_reused_within_request(): void
    {
        $first = $this->product('First Stock', 'first-stock', true);
        $second = $this->product('Second Stock', 'second-stock', true);
        $service = app(InventoryAvailabilityService::class);
        app(InventoryLedgerService::class)->post($this->manager, $second->defaultVariant, StockLocation::main(), MovementType::Receipt, 2, 'Test receipt');
        request()->attributes->remove('inventory.storefront');
        DB::enableQueryLog();
        $map = $service->storefront([$first->id, $second->id]);
        $count = count(DB::getQueryLog());
        $this->assertFalse($map[$first->id][$first->default_variant_id]['is_available']);
        $this->assertSame(2, $map[$second->id][$second->default_variant_id]['available_to_sell']);
        $service->storefront([$first->id]);
        $service->storefront([$second->id]);
        $this->assertSame($count, count(DB::getQueryLog()));
        $this->assertLessThanOrEqual(8, $count);
        DB::disableQueryLog();
    }

    public function test_option_shapes_keep_variant_stock_price_and_gallery_identity(): void
    {
        foreach (['both', 'colour', 'size'] as $shape) {
            $colours = $shape === 'size' ? [] : ['black' => ['name' => 'Black'], 'ivory' => ['name' => 'Ivory']];
            $sizes = $shape === 'colour' ? [] : ['m' => ['name' => 'M'], 'l' => ['name' => 'L']];
            $rows = [];
            foreach (array_keys($colours ?: ['none' => []]) as $colour) {
                foreach (array_keys($sizes ?: ['none' => []]) as $size) {
                    $rows[] = ['key' => $colour.'--'.$size, 'colour_key' => $colour === 'none' ? '' : $colour, 'size_key' => $size === 'none' ? '' : $size, 'label' => $colour.' '.$size, 'sku' => strtoupper($shape.'-'.$colour.'-'.$size), 'price' => count($rows) ? '150000' : ''];
                }
            }
            $product = $this->product('Shape '.$shape, 'shape-'.$shape, true, ['draft_colours' => $colours, 'draft_sizes' => $sizes, 'draft_variants' => $rows, 'default_variant_key' => $rows[0]['key']]);
            $before = $this->get(route('products.show', $product))->assertOk()->viewData('catalogueProduct');
            $stocked = $product->variants()->whereKeyNot($product->default_variant_id)->first();
            app(InventoryLedgerService::class)->post($this->manager, $stocked, StockLocation::main(), MovementType::Receipt, 2, 'Shape receipt');
            $response = $this->get(route('products.show', $product))->assertOk();
            $after = $response->viewData('catalogueProduct');
            $this->assertTrue($after['is_available']);
            $this->assertFalse(collect($after['variants'])->firstWhere('id', $product->default_variant_id)['is_available']);
            $this->assertTrue(collect($after['variants'])->firstWhere('id', $stocked->id)['is_available']);
            $this->assertSame($before['images'], $after['images']);
            $this->assertSame($before['colour_images'], $after['colour_images']);
            $this->assertSame(array_column($before['variants'], 'sku'), array_column($after['variants'], 'sku'));
            $this->assertSame(array_column($before['variants'], 'price'), array_column($after['variants'], 'price'));
            $response->assertSee('Out of stock');
        }
    }

    public function test_new_arrivals_and_collection_share_zero_stock_cards_and_keep_order(): void
    {
        $first = $this->product('First Listed', 'first-listed', true);
        $second = $this->product('Second Listed', 'second-listed', true);
        $collection = $this->collection('Listed Collection', [$second, $first], true);
        HomepageHero::query()->create(['id' => HomepageHero::SINGLETON_ID, ...HomepageHero::defaults(), 'new_arrivals_collection_id' => $collection->id, 'created_by' => $this->manager->id, 'updated_by' => $this->manager->id]);
        $this->get(route('home'))->assertOk()->assertSee('data-inventory-out-of-stock', false)->assertSee(route('products.show', $first), false);
        $cards = $this->get(route('collections.show', $collection))->assertOk()->viewData('productCards');
        $this->assertSame(['second-listed', 'first-listed'], $cards->pluck('slug')->all());
        $this->assertFalse($cards[0]['is_available']);
        $this->assertFalse($cards[1]['is_available']);
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

    private function product(string $title, string $slug, bool $active, array $extra = []): Product
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
            ...$extra,
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
