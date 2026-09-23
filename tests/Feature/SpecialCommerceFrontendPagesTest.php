<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\Support\StorefrontMarkup;
use Tests\TestCase;

class SpecialCommerceFrontendPagesTest extends TestCase
{
    public function test_special_commerce_routes_are_named_public_and_render_identifying_content(): void
    {
        $pages = [
            'preorders.index' => ['/pre-order', 'Reserve exclusive pieces before they launch.'],
            'limited-edition.index' => ['/limited-edition', 'Numbered pieces. Exclusive access.'],
            'gift-cards.index' => ['/gift-cards', 'The gift of sartorial distinction.'],
        ];

        foreach ($pages as $routeName => [$uri, $content]) {
            $this->assertTrue(Route::has($routeName));
            $this->assertSame($uri, route($routeName, absolute: false));
            $this->get(route($routeName))->assertOk()->assertSeeText($content);
        }
    }

    public function test_special_commerce_pages_preserve_assets_and_single_regions(): void
    {
        foreach (['preorders.index', 'limited-edition.index', 'gift-cards.index'] as $routeName) {
            $html = StorefrontMarkup::active($this->get(route($routeName))->assertOk()->getContent());

            $this->assertStringNotContainsString('Sign the Ledger', $html);

            foreach (['<html lang="en">', '<head>', '<body>', 'aria-label="Close announcement"', '<header data-canonical-shop-header data-smart-header="top"', '<footer data-canonical-storefront-footer class="bg-wt-oxblood border-t border-wt-gold/30 pb-16 lg:pb-0 relative overflow-hidden">', '<div class="lg:hidden fixed bottom-0 left-0 right-0 z-40">', 'aria-label="Chat on WhatsApp"', '/website/css/index-X8-QjRMe.css'] as $needle) {
                $this->assertSame(1, substr_count($html, $needle), "Unexpected region count for {$needle} on {$routeName}");
            }
            $this->assertSame(in_array($routeName, ['preorders.index', 'limited-edition.index'], true) ? 0 : 1, substr_count($html, '/website/js/index-DxdnTNDA.js'));

            $this->assertStringNotContainsString('src="images/', $html);
            $this->assertStringNotContainsString('href="css/', $html);
            $this->assertStringNotContainsString('src="js/', $html);
        }
    }

    public function test_each_page_preserves_its_supplied_form_count_and_controls(): void
    {
        $preorder = StorefrontMarkup::active($this->get(route('preorders.index'))->assertOk()->getContent());
        $limited = StorefrontMarkup::active($this->get(route('limited-edition.index'))->assertOk()->getContent());
        $giftCards = StorefrontMarkup::active($this->get(route('gift-cards.index'))->assertOk()->getContent());

        $this->assertSame(1, substr_count($preorder, '<form'));
        $this->assertSame(1, substr_count($preorder, 'type="submit"'));
        $this->assertSame(0, substr_count($limited, '<form'));
        $this->assertSame(5, substr_count($limited, 'aria-label="Add to wishlist"'));
        $this->assertStringNotContainsString('Only 8 left', $limited);
        $this->assertStringNotContainsString('Only 30 Made', $limited);
        $this->assertSame(1, substr_count($giftCards, '<form'));
        preg_match('/<main\b[^>]*>(.*?)<\/main>/s', $giftCards, $main);
        $this->assertSame(9, substr_count($main[1], 'type="button"'));
        $this->assertSame(5, substr_count($giftCards, 'required=""'));
        $this->assertSame(1, substr_count($giftCards, '<textarea'));
        $this->assertStringContainsString('Purchase Gift Card', $giftCards);
    }
}
