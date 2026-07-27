<?php

namespace App\Domain\PublicProjection\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

final class PublicSiteContentCache
{
    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    public function remember(string $resourceId, string $key, Closure $build): mixed
    {
        $value = Cache::remember($key, (int) config('public_site_content.cache_ttl_seconds'), $build);
        $indexKey = $this->indexKey($resourceId);
        $keys = Cache::get($indexKey, []);
        if (! is_array($keys)) {
            $keys = [];
        }
        $keys[] = $key;
        Cache::put($indexKey, array_values(array_unique($keys)), (int) config('public_site_content.cache_ttl_seconds'));

        return $value;
    }

    public function invalidate(string $resourceId): void
    {
        $indexKey = $this->indexKey($resourceId);
        $keys = Cache::pull($indexKey, []);
        if (! is_array($keys)) {
            return;
        }
        foreach ($keys as $key) {
            if (is_string($key)) {
                Cache::forget($key);
            }
        }
    }

    private function indexKey(string $resourceId): string
    {
        return 'public-site-content:index:'.$resourceId;
    }
}
