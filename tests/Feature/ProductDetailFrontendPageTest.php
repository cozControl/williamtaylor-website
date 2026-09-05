<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProductDetailFrontendPageTest extends TestCase
{
    public function test_fe_2d_product_detail_route_is_public_named_and_renders_source_content(): void
    {
        $this->assertTrue(Route::has('products.taylor-oxford-shirt'));

        $this->get(route('products.taylor-oxford-shirt'))
            ->assertOk()
            ->assertSeeText('The Taylor Oxford Shirt')
            ->assertSeeText('TZS 285,000')
            ->assertSee('/website/css/index-X8-QjRMe.css', false)
            ->assertSee('/website/js/index-DxdnTNDA.js', false);
    }

    public function test_product_detail_reuses_shared_regions_once_and_preserves_static_controls(): void
    {
        $html = $this->get(route('products.taylor-oxford-shirt'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-label="Close announcement"'));
        $this->assertSame(1, substr_count($html, '<footer'));
        $this->assertSame(1, substr_count($html, 'Sign the Ledger'));
        $this->assertSame(1, substr_count($html, 'aria-label="Chat on WhatsApp"'));
        $this->assertSame(1, substr_count($html, 'Add to Cart'));
        $this->assertSame(1, substr_count($html, 'Buy Now'));
        $this->assertSame(1, substr_count($html, 'Add to Wishlist'));
        $this->assertSame(1, substr_count($html, 'Reviews (20)'));
        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertSame(0, substr_count($html, '<form action='));
        $this->assertSame(0, substr_count($html, 'wire:'));
    }

    public function test_product_detail_registers_canonical_dynamic_route_without_commerce_mutations(): void
    {
        $this->assertTrue(Route::has('products.show'));
        $this->assertFalse(Route::has('cart.store'));
        $this->assertFalse(Route::has('wishlist.store'));
        $this->assertFalse(Route::has('checkout.store'));
        $this->assertFalse(Route::has('orders.store'));
    }

    public function test_fe_2e_mercerized_cotton_polo_route_is_public_named_and_renders_source_content(): void
    {
        $this->assertTrue(Route::has('products.mercerized-cotton-polo'));
        $this->assertSame('/products/mercerized-cotton-polo', route('products.mercerized-cotton-polo', absolute: false));

        $this->get(route('products.mercerized-cotton-polo'))
            ->assertOk()
            ->assertSeeText('Mercerized Cotton Polo')
            ->assertSeeText('TZS 195,000')
            ->assertSee('/website/images/db23eed31_image.jpg', false)
            ->assertSee('/website/css/index-X8-QjRMe.css', false)
            ->assertSee('/website/js/index-DxdnTNDA.js', false);
    }

    public function test_fe_2e_polo_reuses_shared_regions_and_keeps_commerce_controls_presentational(): void
    {
        $html = $this->get(route('products.mercerized-cotton-polo'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-label="Close announcement"'));
        $this->assertSame(1, substr_count($html, '<footer'));
        $this->assertSame(1, substr_count($html, 'Sign the Ledger'));
        $this->assertSame(1, substr_count($html, 'aria-label="Chat on WhatsApp"'));
        $this->assertSame(1, substr_count($html, 'Add to Cart'));
        $this->assertSame(1, substr_count($html, 'Buy Now'));
        $this->assertSame(1, substr_count($html, 'Add to Wishlist'));
        $this->assertSame(1, substr_count($html, 'Reviews (7)'));
        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertSame(0, substr_count($html, '<form action='));
        $this->assertSame(0, substr_count($html, 'wire:'));
    }

    public function test_fe_2f_dar_es_salaam_linen_suit_route_is_public_named_and_renders_source_content(): void
    {
        $this->assertTrue(Route::has('products.dar-es-salaam-linen-suit'));
        $this->assertSame('/products/the-dar-es-salaam-linen-suit', route('products.dar-es-salaam-linen-suit', absolute: false));

        $this->get(route('products.dar-es-salaam-linen-suit'))
            ->assertOk()
            ->assertSeeText('The Dar es Salaam Linen Suit')
            ->assertSeeText('TZS 1,250,000')
            ->assertSee('/website/images/da608a583_image.jpg', false)
            ->assertSee('/website/css/index-X8-QjRMe.css', false)
            ->assertSee('/website/js/index-DxdnTNDA.js', false);
    }

    public function test_fe_2f_linen_suit_reuses_shared_regions_and_keeps_commerce_controls_presentational(): void
    {
        $html = $this->get(route('products.dar-es-salaam-linen-suit'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-label="Close announcement"'));
        $this->assertSame(1, substr_count($html, '<footer'));
        $this->assertSame(1, substr_count($html, 'Sign the Ledger'));
        $this->assertSame(1, substr_count($html, 'aria-label="Chat on WhatsApp"'));
        $this->assertSame(1, substr_count($html, 'Add to Cart'));
        $this->assertSame(1, substr_count($html, 'Buy Now'));
        $this->assertSame(1, substr_count($html, 'Add to Wishlist'));
        $this->assertSame(1, substr_count($html, 'Reviews (6)'));
        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertSame(0, substr_count($html, '<form action='));
        $this->assertSame(0, substr_count($html, 'wire:'));
    }

    public function test_fe_2g_slim_tapered_chinos_route_is_public_named_and_renders_source_content(): void
    {
        $this->assertTrue(Route::has('products.slim-tapered-chinos'));
        $this->assertSame('/products/slim-tapered-chinos', route('products.slim-tapered-chinos', absolute: false));

        $this->get(route('products.slim-tapered-chinos'))
            ->assertOk()
            ->assertSeeText('Slim Tapered Chinos')
            ->assertSeeText('TZS 245,000')
            ->assertSee('/website/images/8d7122778_image.jpg', false)
            ->assertSee('title="Sage"', false)
            ->assertSee('title="Cream"', false)
            ->assertSeeText('XS')
            ->assertSeeText('S')
            ->assertSeeText('M')
            ->assertSeeText('L')
            ->assertSeeText('XL')
            ->assertSeeText('XXL')
            ->assertSeeText('3XL')
            ->assertSee('/website/css/index-X8-QjRMe.css', false)
            ->assertSee('/website/js/index-DxdnTNDA.js', false);
    }

    public function test_fe_2g_chinos_reuses_shared_regions_and_keeps_commerce_controls_presentational(): void
    {
        $html = $this->get(route('products.slim-tapered-chinos'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-label="Close announcement"'));
        $this->assertSame(1, substr_count($html, '<footer'));
        $this->assertSame(1, substr_count($html, 'Sign the Ledger'));
        $this->assertSame(1, substr_count($html, 'aria-label="Chat on WhatsApp"'));
        $this->assertSame(1, substr_count($html, 'title="Sage"'));
        $this->assertSame(1, substr_count($html, 'title="Cream"'));
        $this->assertSame(1, substr_count($html, 'lucide lucide-minus'));
        $this->assertSame(1, substr_count($html, 'lucide lucide-plus'));
        $this->assertSame(1, substr_count($html, 'Add to Cart'));
        $this->assertSame(1, substr_count($html, 'Buy Now'));
        $this->assertSame(1, substr_count($html, 'Add to Wishlist'));
        $this->assertSame(1, substr_count($html, 'Reviews (26)'));
        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertSame(0, substr_count($html, '<form action='));
        $this->assertSame(0, substr_count($html, 'wire:'));
    }

    public function test_fe_2h_executive_overcoat_route_is_public_named_and_renders_source_content(): void
    {
        $this->assertTrue(Route::has('products.executive-overcoat'));
        $this->assertSame('/products/the-executive-overcoat', route('products.executive-overcoat', absolute: false));

        $this->get(route('products.executive-overcoat'))
            ->assertOk()
            ->assertSeeText('The Executive Overcoat')
            ->assertSeeText('TZS 890,000')
            ->assertSeeText('PRE-ORDER')
            ->assertSeeText('Ships 2026-08-15')
            ->assertSeeText('S')
            ->assertSeeText('M')
            ->assertSeeText('L')
            ->assertSeeText('XL')
            ->assertSeeText('XXL')
            ->assertSee('/website/images/81f56a965_image.jpg', false)
            ->assertSee('/website/css/index-X8-QjRMe.css', false)
            ->assertSee('/website/js/index-DxdnTNDA.js', false);
    }

    public function test_fe_2h_overcoat_reuses_shared_regions_and_keeps_preorder_controls_presentational(): void
    {
        $html = $this->get(route('products.executive-overcoat'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'aria-label="Close announcement"'));
        $this->assertSame(1, substr_count($html, '<footer'));
        $this->assertSame(1, substr_count($html, 'Sign the Ledger'));
        $this->assertSame(1, substr_count($html, 'aria-label="Chat on WhatsApp"'));
        $this->assertSame(1, substr_count($html, 'lucide lucide-minus'));
        $this->assertSame(1, substr_count($html, 'lucide lucide-plus'));
        $this->assertSame(1, substr_count($html, 'Reserve Your Piece'));
        $this->assertSame(0, substr_count($html, 'Add to Cart'));
        $this->assertSame(1, substr_count($html, 'Buy Now'));
        $this->assertSame(1, substr_count($html, 'Add to Wishlist'));
        $this->assertSame(1, substr_count($html, 'Reviews (9)'));
        $this->assertSame(0, substr_count($html, 'Colour:'));
        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertSame(0, substr_count($html, '<form action='));
        $this->assertSame(0, substr_count($html, 'wire:'));
    }
}
