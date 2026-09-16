<?php

namespace Tests\Feature\Cart;

use App\Domain\Cart\CartService;
use App\Domain\Catalogue\Models\Product;
use App\Domain\Catalogue\Models\ProductCategory;
use App\Domain\Catalogue\Models\ProductOptionValue;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Services\InventoryAvailabilityService;
use App\Domain\Inventory\Services\InventoryLedgerService;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CategoryOwner;
use Tests\TestCase;

final class CartTest extends TestCase
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

    public function test_guest_cart_flow_uses_current_stock_and_never_posts_inventory(): void
    {
        $product = $this->product('Cart Shirt', 'cart-shirt', true);
        $variant = $product->defaultVariant;
        $ledger = app(InventoryLedgerService::class);
        $location = StockLocation::main();
        $ledger->post($this->manager, $variant, $location, MovementType::Opening, 3, 'Cart fixture');
        auth()->forgetGuards();
        $movements = DB::table('inventory_movements')->count();
        $this->get('/cart')->assertOk()->assertSee('Your cart is empty');
        $this->get(route('products.show', $product))->assertOk()->assertSee('data-cart-add', false);
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1, 'price' => 1, 'title' => 'Fake', 'sku' => 'Fake', 'subtotal' => 0])->assertOk()->assertJsonPath('cart.item_count', 1)->assertJsonPath('cart.lines.0.unit_price_minor', 12500000)->assertJsonPath('cart.subtotal_minor', 12500000)->assertJsonPath('cart.is_checkout_ready', true);
        $this->get('/cart')->assertOk()->assertSee('Cart Shirt')->assertDontSee('Fake');
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertOk()->assertJsonCount(1, 'cart.lines')->assertJsonPath('cart.item_count', 2);
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 2])->assertUnprocessable()->assertJsonPath('cart.item_count', 2);
        $this->patchJson('/cart/items/'.$variant->id, ['quantity' => 3])->assertOk()->assertJsonPath('cart.subtotal_minor', 37500000);
        $this->patchJson('/cart/items/'.$variant->id, ['quantity' => 4])->assertUnprocessable()->assertJsonPath('cart.item_count', 3);
        $this->assertSame($movements, DB::table('inventory_movements')->count());
        $ledger->count($this->manager, $variant, $location, 1, 3, 'External count');
        $this->getJson('/cart')->assertOk()->assertJsonPath('cart.lines.0.quantity', 3)->assertJsonPath('cart.is_checkout_ready', false)->assertJsonPath('cart.lines.0.available_to_sell', 1);
        $ledger->count($this->manager, $variant, $location, 0, 1, 'External zero');
        $this->getJson('/cart')->assertOk()->assertJsonPath('cart.lines.0.quantity', 3)->assertJsonPath('cart.lines.0.is_available', false);
        $this->deleteJson('/cart/items/'.$variant->id)->assertOk()->assertJsonPath('cart.item_count', 0)->assertJsonPath('cart.is_empty', true);
        $this->get('/cart')->assertOk()->assertSee('Your cart is empty');
        $this->assertSame($movements + 2, DB::table('inventory_movements')->count());
        $this->assertSame(0, app(InventoryAvailabilityService::class)->onHand($variant));
        $this->assertSame([], session('commerce_cart'));
    }

    public function test_invalid_inputs_and_stale_identities_fail_closed(): void
    {
        $product = $this->product('Invalid Shirt', 'invalid-shirt', true);
        $variant = $product->defaultVariant;
        foreach ([0, -1, 1.5, 'abc', '', null, []] as $quantity) {
            $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => $quantity])->assertUnprocessable();
        }
        $this->postJson('/cart/items', ['variant_id' => (string) Str::ulid(), 'quantity' => 1])->assertUnprocessable();
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertUnprocessable();
        app(InventoryLedgerService::class)->post($this->manager, $variant, StockLocation::main(), MovementType::Receipt, 3, 'Fixture');
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertOk();
        $variant->update(['price_override_minor' => 9900000]);
        $this->getJson('/cart')->assertJsonPath('cart.lines.0.unit_price_minor', 9900000);
        $product->update(['catalogue_status' => 'draft']);
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertUnprocessable();
        $this->getJson('/cart')->assertJsonPath('cart.is_checkout_ready', false)->assertJsonCount(1, 'cart.lines');
        $variant->update(['archived_at' => now()]);
        $this->getJson('/cart')->assertOk()->assertJsonPath('cart.is_checkout_ready', false);
        $this->withSession(['commerce_cart' => ['bad' => 2, (string) Str::ulid() => 1, $variant->id => ['bad']]])->getJson('/cart')->assertOk()->assertJsonCount(1, 'cart.lines')->assertJsonPath('cart.lines.0.title', 'Unavailable item');
        $this->withSession(['commerce_cart' => 'corrupt'])->getJson('/cart')->assertOk()->assertJsonPath('cart.is_empty', true);
    }

    public function test_option_shapes_separate_variants_and_bulk_reconstruction(): void
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
            foreach ($product->variants as $variant) {
                app(InventoryLedgerService::class)->post($this->manager, $variant, StockLocation::main(), MovementType::Receipt, 3, 'Fixture');
                $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertOk();
            }
        }
        $this->getJson('/cart')->assertOk()->assertJsonCount(8, 'cart.lines')->assertJsonPath('cart.item_count', 8)->assertJsonPath('cart.subtotal_minor', 112500000);
        DB::enableQueryLog();
        app(CartService::class)->snapshot();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, array_filter($queries, fn ($query) => str_contains($query['query'], 'from "inventory_balances"')));
        $this->assertLessThanOrEqual(18, count($queries));
    }

    public function test_current_metadata_and_invalid_media_reconstruct_without_snapshots(): void
    {
        $product = $this->product('Before', 'metadata', true);
        $variant = $product->defaultVariant;
        app(InventoryLedgerService::class)->post($this->manager, $variant, StockLocation::main(), MovementType::Receipt, 2, 'Fixture');
        $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 1])->assertOk();
        $revision = $product->currentDraftRevision->replicate();
        $revision->title = 'Current title';
        $revision->created_at = now();
        $revision->revision_number++;
        $revision->checksum = hash('sha256', 'Current title');
        $revision->save();
        $product->update(['current_draft_revision_id' => $revision->id]);
        $variant->update(['sku' => 'CURRENT-SKU']);
        $usage = MediaUsage::where('owner_identifier', $product->id)->firstOrFail();
        $replacement = $this->image('replacement', 'Current image');
        $usage->update(['media_asset_id' => $replacement->id, 'alt_text_override' => 'Current image']);
        $this->getJson('/cart')->assertOk()->assertJsonPath('cart.lines.0.title', 'Current title')->assertJsonPath('cart.lines.0.sku', 'CURRENT-SKU')->assertJsonPath('cart.lines.0.image.alt', 'Current image');
        $this->assertSame([$variant->id => 1], session('commerce_cart'));
        $replacement->update(['confirmed_at' => null]);
        $this->getJson('/cart')->assertOk()->assertJsonPath('cart.is_checkout_ready', false)->assertJsonPath('cart.lines.0.image', null);
        $movements = DB::table('inventory_movements')->count();
        app(CartService::class)->clear();
        $this->assertSame([], app(CartService::class)->lines());
        $this->assertSame($movements, DB::table('inventory_movements')->count());
    }

    public function test_unusable_colour_image_falls_back_to_product_image_in_cart_and_drawer(): void
    {
        $product = $this->product('Colour Shirt', 'colour-shirt', true, [
            'draft_colours' => ['black' => ['name' => 'Black']],
            'draft_variants' => [['key' => 'black--none', 'colour_key' => 'black', 'size_key' => '', 'label' => 'Black', 'sku' => 'COLOUR-BLACK', 'price' => '']],
            'default_variant_key' => 'black--none',
        ]);
        $variant = $product->defaultVariant;
        $primary = MediaUsage::with('asset')->where('owner_identifier', $product->id)->where('field_role', 'primary')->sole();
        $colourImage = $this->image('colour-image', '');
        $usage = MediaUsage::query()->create(['media_asset_id' => $colourImage->id, 'owner_type' => ProductOptionValue::class, 'owner_identifier' => $variant->values->sole()->id, 'field_role' => 'colour_primary', 'sort_order' => 0]);
        app(InventoryLedgerService::class)->post($this->manager, $variant, StockLocation::main(), MovementType::Receipt, 5, 'Image fixture');
        $expected = app(MediaProvider::class)->deliveryUrl($primary->asset->provider_public_id, 'image', 'product_gallery');
        $response = $this->postJson('/cart/items', ['variant_id' => $variant->id, 'quantity' => 3])->assertOk()->assertJsonPath('cart.lines.0.image.url', $expected)->assertJsonPath('cart.lines.0.quantity', 3);
        $this->assertStringContainsString('src="'.$expected.'"', $response->json('html'));
        $this->get('/cart')->assertOk()->assertSee($expected, false);
        $usage->update(['alt_text_override' => 'Black shirt']);
        $this->getJson('/cart')->assertOk()->assertJsonPath('cart.lines.0.image.alt', 'Black shirt');
        $this->assertDatabaseCount('inventory_movements', 1);
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
