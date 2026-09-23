<?php

namespace Tests\Feature;

use Tests\Support\StorefrontMarkup;
use Tests\TestCase;

class AccountFrontendPagesTest extends TestCase
{
    public function test_selected_fe_2c_routes_are_public_named_and_branded(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeText('Welcome back')
            ->assertSee('/website/css/index-X8-QjRMe.css', false)
            ->assertDontSee('/website/js/index-DxdnTNDA.js', false);

        $this->get(route('wishlist.index'))
            ->assertOk()
            ->assertSeeText('Your wishlist is empty')
            ->assertSee('/website/css/index-X8-QjRMe.css', false)
            ->assertDontSee('/website/js/index-DxdnTNDA.js', false)
            ->assertSee('/website/js/catalogue-wishlist.js', false);
    }

    public function test_login_preserves_existing_fortify_contract_without_storefront_regions(): void
    {
        $html = StorefrontMarkup::active($this->get(route('login'))->assertOk()->getContent());

        $this->assertSame(1, substr_count($html, '<form'));
        $this->assertSame(1, substr_count($html, 'action="'.route('login.store').'"'));
        $this->assertSame(1, substr_count($html, 'name="email"'));
        $this->assertSame(1, substr_count($html, 'name="password"'));
        $this->assertSame(0, substr_count($html, 'aria-label="Close announcement"'));
        $this->assertSame(0, substr_count($html, '<footer'));
        $this->assertSame(0, substr_count($html, 'aria-label="Chat on WhatsApp"'));
    }

    public function test_wishlist_reuses_exact_shared_regions_once_and_remains_empty_static_presentation(): void
    {
        $html = StorefrontMarkup::active($this->get(route('wishlist.index'))->assertOk()->getContent());

        $this->assertSame(1, substr_count($html, 'aria-label="Close announcement"'));
        $this->assertSame(1, substr_count($html, '<footer'));
        $this->assertStringNotContainsString('Sign the Ledger', $html);
        $this->assertSame(1, substr_count($html, 'aria-label="Chat on WhatsApp"'));
        $this->assertSame(1, substr_count($html, 'Your wishlist is empty'));
        $this->assertSame(0, substr_count($html, 'aria-label="Add to wishlist"'));
        $this->assertSame(0, substr_count($html, '<form'));
        $this->assertSame(0, substr_count($html, '<form action='));
    }
}
