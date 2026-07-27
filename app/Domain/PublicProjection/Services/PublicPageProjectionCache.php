<?php

namespace App\Domain\PublicProjection\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

final class PublicPageProjectionCache
{
    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    public function remember(string $pageId, string $key, Closure $build): mixed
    {
        $value = Cache::remember($key, (int) config('public_page_projection.cache_ttl_seconds'), $build);
        $index = $this->index($pageId);
        $keys = Cache::get($index, []);
        Cache::put($index, array_values(array_unique([...is_array($keys) ? $keys : [], $key])), (int) config('public_page_projection.cache_ttl_seconds'));

        return $value;
    }

    public function invalidate(string $pageId): void
    {
        $keys = Cache::pull($this->index($pageId), []);
        foreach (is_array($keys) ? $keys : [] as $key) {
            if (is_string($key)) {
                Cache::forget($key);
            }
        }
    }

    private function index(string $pageId): string
    {
        return 'public-page:index:'.$pageId;
    }
}
