<?php

namespace App\Domain\Campaign\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

final class CampaignConfigurationCache
{
    /** @param Closure():array<string,mixed> $build
     * @return array<string,mixed> */
    public function remember(string $id, Closure $build): array
    {
        return Cache::remember('campaign:configuration:v1:'.$id, 3600, $build);
    }

    public function invalidate(string $id): void
    {
        Cache::forget('campaign:configuration:v1:'.$id);
    }
}
