<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Tests\Support\StorefrontMarkup;
use Tests\TestCase;

class SharedFrontendRegionsTest extends TestCase
{
    public function test_shared_frontend_partials_exist_and_render_once_on_the_guest_homepage(): void
    {
        foreach ([
            'frontend.partials.document-head',
            'frontend.partials.announcement',
            'frontend.partials.header',
            'frontend.partials.newsletter',
            'frontend.partials.footer',
            'frontend.partials.mobile-bottom-navigation',
            'frontend.partials.whatsapp-action',
        ] as $partial) {
            $this->assertTrue(View::exists($partial), "Missing shared partial: {$partial}");
        }

        $response = $this->get(route('home'))->assertOk();
        $html = StorefrontMarkup::active($response->getContent());

        $this->assertSame(1, substr_count($html, '<html lang="en">'));
        $this->assertSame(1, substr_count($html, '<head>'));
        $this->assertSame(1, substr_count($html, '<body>'));
        $this->assertSame(1, substr_count($html, 'aria-label="Close announcement"'));
        $this->assertSame(1, substr_count($html, '<header data-canonical-shop-header class="fixed top-0 left-0 right-0 z-40">'));
        $this->assertSame(1, substr_count($html, '<div class="lg:hidden fixed bottom-0 left-0 right-0 z-40">'));
        $this->assertSame(1, substr_count($html, 'Sign the Ledger'));
        $this->assertSame(1, substr_count($html, '<footer class="bg-wt-oxblood border-t border-wt-gold/30 pb-16 lg:pb-0 relative overflow-hidden">'));
        $this->assertSame(1, substr_count($html, 'aria-label="Chat on WhatsApp"'));
        $this->assertSame(1, substr_count($html, '/website/css/index-X8-QjRMe.css'));
        $this->assertSame(1, substr_count($html, '/website/js/index-DxdnTNDA.js'));
        $this->assertSame(2, substr_count($html, '/website/images/8d99836ea_LOGO-3.png'));
    }
}
