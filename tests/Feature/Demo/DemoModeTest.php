<?php

namespace Tests\Feature\Demo;

use App\Support\Demo\DemoMode;
use Tests\TestCase;

final class DemoModeTest extends TestCase
{
    public function test_demo_mode_defaults_off(): void
    {
        config()->set('demo.enabled', false);
        config()->set('publication_rollout.global_enabled', true);

        $this->assertFalse(app(DemoMode::class)->configured());
        $this->assertFalse(app(DemoMode::class)->enabled());
    }

    public function test_demo_mode_is_subordinate_to_global_kill_switch(): void
    {
        config()->set('demo.enabled', true);
        config()->set('demo.allowed_environments', ['testing']);
        config()->set('publication_rollout.global_enabled', false);

        $this->assertTrue(app(DemoMode::class)->configured());
        $this->assertFalse(app(DemoMode::class)->enabled());
    }

    public function test_demo_mode_can_enable_only_in_an_allowed_environment(): void
    {
        config()->set('demo.enabled', true);
        config()->set('demo.allowed_environments', ['testing']);
        config()->set('publication_rollout.global_enabled', true);

        $this->assertTrue(app(DemoMode::class)->enabled());

        config()->set('demo.allowed_environments', ['demo']);
        $this->assertFalse(app(DemoMode::class)->configured());
        $this->assertFalse(app(DemoMode::class)->enabled());
    }
}
