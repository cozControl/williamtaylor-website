<?php

namespace Tests\Feature;

use App\Domain\PublicProjection\Data\PublicSiteChromeView;
use App\Domain\PublicProjection\Data\PublicSiteProfileView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Tests\Support\StorefrontMarkup;
use Tests\TestCase;

final class MinimalStorefrontHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_has_only_brand_and_three_named_utilities_in_both_header_projections(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $headers = $xpath->query('//header[@data-canonical-shop-header]');
        $this->assertCount(2, $headers);
        foreach ($headers as $header) {
            $this->assertSame('top', $header->getAttribute('data-smart-header'));
            $this->assertCount(4, $xpath->query('.//nav//a | .//nav//button', $header));
            $this->assertCount(0, $xpath->query('.//*[@data-shop-navigation or @data-public-menu-open or @data-public-back]', $header));
            $this->assertSame(route('search'), $xpath->query('.//a[@aria-label="Search"]', $header)->item(0)->getAttribute('href'));
            $this->assertSame(route('login'), $xpath->query('.//a[@aria-label="Sign in"]', $header)->item(0)->getAttribute('href'));
            $this->assertCount(1, $xpath->query('.//button[@data-cart-open and @aria-haspopup="dialog"]', $header));
            $this->assertCount(1, $xpath->query('.//*[@data-cart-badge and @hidden]', $header));
        }
    }

    public function test_authenticated_profile_and_session_cart_quantity_are_preserved(): void
    {
        $this->actingAs(User::factory()->create());
        $html = StorefrontMarkup::active($this->withSession(['commerce_cart' => [(string) Str::ulid() => 3]])->get(route('home'))->assertOk()->getContent());
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(route('profile.edit'), $xpath->query('//header//a[@aria-label="My account"]')->item(0)->getAttribute('href'));
        $this->assertSame('3', trim($xpath->query('//header//*[@data-cart-count]')->item(0)->textContent));
        $this->assertCount(0, $xpath->query('//header//*[@data-cart-badge and @hidden]'));
        $this->assertCount(1, $xpath->query('//header//button[@aria-label="Open cart, 3 items"]'));
    }

    public function test_header_uses_the_resolved_site_profile_asset_and_brand(): void
    {
        $profile = new PublicSiteProfileView('Configured Brand', '', '/configured-logo.svg', null, '', '', '', '', '', [], '', '', '', '');
        $html = Blade::render(file_get_contents(resource_path('views/frontend/partials/header.blade.php')), ['publicSiteChrome' => new PublicSiteChromeView(null, null, null, $profile)]);
        $this->assertStringContainsString('src="/configured-logo.svg"', $html);
        $this->assertStringContainsString('aria-label="Configured Brand home"', $html);
        $this->assertStringNotContainsString('/website/images/8d99836ea_LOGO-3.png', $html);
    }
}
