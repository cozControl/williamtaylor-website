<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\StorefrontMarkup;
use Tests\TestCase;

class CatalogueFrontendPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_collections_and_shop_routes_are_named_and_public_for_guests(): void
    {
        $this->assertSame('/collections', route('collections.index', absolute: false));
        $this->assertSame('/shop', route('products.index', absolute: false));
        $this->assertTrue(Route::has('collections.index'));
        $this->assertTrue(Route::has('products.index'));

        $this->get(route('collections.index'))->assertOk()->assertSeeText('Collections');
        $this->get(route('products.index'))->assertOk()->assertSeeText('Shop All');
    }

    public function test_catalogue_pages_preserve_assets_and_single_shared_regions(): void
    {
        foreach (['collections.index', 'products.index'] as $routeName) {
            $html = StorefrontMarkup::active($this->get(route($routeName))->assertOk()->getContent());

            $this->assertSame(1, substr_count($html, '<html lang="en">'));
            $this->assertSame(1, substr_count($html, '<head>'));
            $this->assertSame(1, substr_count($html, '<body>'));
            $this->assertSame(1, substr_count($html, 'aria-label="Close announcement"'));
            $this->assertSame(1, substr_count($html, '<header data-canonical-shop-header data-smart-header="top"'));
            $this->assertSame(1, substr_count($html, 'Sign the Ledger'));
            $this->assertSame(1, substr_count($html, '<footer class="bg-wt-oxblood border-t border-wt-gold/30 pb-16 lg:pb-0 relative overflow-hidden">'));
            $this->assertSame(1, substr_count($html, '<div class="lg:hidden fixed bottom-0 left-0 right-0 z-40">'));
            $this->assertSame(1, substr_count($html, 'aria-label="Chat on WhatsApp"'));
            $this->assertSame(1, substr_count($html, '/website/css/index-X8-QjRMe.css'));
            $this->assertSame(0, substr_count($html, '/website/js/index-DxdnTNDA.js'));
            $this->assertSame(1, substr_count($html, '/website/js/cart.js'));
            $this->assertStringNotContainsString('src="images/', $html);
            $this->assertStringNotContainsString('href="css/', $html);
            $this->assertStringNotContainsString('src="js/', $html);
            $this->assertStringContainsString('href="'.route('search').'"', $html);
            $this->assertStringContainsString('href="'.route('login').'"', $html);
        }
    }

    public function test_empty_global_shop_preserves_catalogue_controls_and_truthful_count(): void
    {
        $response = $this->get(route('products.index'))->assertOk();

        $response
            ->assertSeeText('0 pieces')
            ->assertSeeText('Filters')
            ->assertSeeText('Newest')
            ->assertSee('data-catalogue-empty', false);
    }
}
