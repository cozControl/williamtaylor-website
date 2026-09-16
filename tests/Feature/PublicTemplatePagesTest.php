<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTemplatePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplied_public_pages_render_from_named_laravel_routes(): void
    {
        $pages = [
            'collections.index' => 'Collections',
            'products.index' => 'Shop All',
            'preorders.index' => 'Pre-Order',
            'limited-edition.index' => 'Limited Edition',
            'gift-cards.index' => 'William Taylor Gift Cards',
            'wishlist.index' => 'Wishlist',
            'products.taylor-oxford-shirt' => 'The Taylor Oxford Shirt',
            'products.mercerized-cotton-polo' => 'Mercerized Cotton Polo',
            'products.dar-es-salaam-linen-suit' => 'The Dar es Salaam Linen Suit',
            'products.slim-tapered-chinos' => 'Slim Tapered Chinos',
            'products.executive-overcoat' => 'The Executive Overcoat',
        ];

        foreach ($pages as $route => $heading) {
            $this->get(route($route))
                ->assertOk()
                ->assertSeeText($heading)
                ->assertSee('/website/css/index-X8-QjRMe.css', false)
                ->assertSee('/website/js/cart.js', false);
            if (in_array($route, ['products.index', 'gift-cards.index', 'wishlist.index'], true)) {
                $this->get(route($route))->assertSee('/website/js/index-DxdnTNDA.js', false);
            } else {
                $this->get(route($route))->assertDontSee('/website/js/index-DxdnTNDA.js', false);
            }
        }
    }

    public function test_supplied_login_design_posts_to_fortify(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome back')
            ->assertSee('The Inner Circle')
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false);
    }

    public function test_known_template_navigation_uses_laravel_routes(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('href="'.route('collections.index').'"', false)
            ->assertSee('href="'.route('products.index').'"', false)
            ->assertSee('href="'.route('wishlist.index').'"', false)
            ->assertSee('href="'.route('login').'"', false);
    }
}
