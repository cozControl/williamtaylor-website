<?php

namespace App\Domain\Catalogue\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

final class CollectionConfigurationCache
{
    /** @param Closure(): array<string, mixed> $build
     * @return array<string, mixed>
     */
    public function remember(string $collectionId, Closure $build): array
    {
        /** @var array<string, mixed> */
        return Cache::remember($this->key($collectionId), 3600, $build);
    }

    public function invalidate(string $collectionId): void
    {
        Cache::forget($this->key($collectionId));
    }

    private function key(string $collectionId): string
    {
        return 'catalogue:collection-configuration:v1:'.$collectionId;
    }
}
