<?php

namespace App\Support\Demo;

final class DemoMode
{
    public function configured(): bool
    {
        return (bool) config('demo.enabled', false)
            && in_array(app()->environment(), config('demo.allowed_environments', []), true);
    }

    public function enabled(): bool
    {
        return $this->configured()
            && (bool) config('publication_rollout.global_enabled', false);
    }
}
