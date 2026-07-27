<?php

namespace App\Domain\Publication\Support;

use App\Domain\Publication\Enums\RolloutMode;

final class RolloutModeResolver
{
    public function resolve(string $resource): RolloutMode
    {
        $definition = app(PublicationResourceRegistry::class)->get($resource);
        if (! config('publication_rollout.global_enabled', false)) {
            return RolloutMode::Static;
        }
        $value = (string) config("publication_rollout.resources.{$resource}", 'static');
        if (! in_array($value, $definition->modes, true)) {
            return RolloutMode::Static;
        }

        return RolloutMode::tryFrom($value) ?? RolloutMode::Static;
    }
}
