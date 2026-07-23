<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_the_client_template(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('William Taylor')
            ->assertSee('Contemporary Menswear')
            ->assertSee('/website/css/index-X8-QjRMe.css', false)
            ->assertSee('/website/js/index-DxdnTNDA.js', false)
            ->assertSee('/website/images/8d99836ea_LOGO-3.png', false)
            ->assertDontSee('Let&#039;s get started', false);
    }
}
