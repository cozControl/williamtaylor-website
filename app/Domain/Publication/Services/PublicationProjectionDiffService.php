<?php

namespace App\Domain\Publication\Services;

use App\Domain\Publication\Data\ProjectionDiffResult;
use App\Domain\Publication\Support\PublicationResourceRegistry;
use Carbon\CarbonImmutable;

final class PublicationProjectionDiffService
{
    public function __construct(private PublicationResourceRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $static
     * @param  array<string, mixed>  $dynamic
     */
    public function compare(string $resourceKey, array $static, array $dynamic, bool $ready, ?CarbonImmutable $now = null): ProjectionDiffResult
    {
        $definition = $this->registry->get($resourceKey);
        if ($definition->projectionResolver === null) {
            throw new \InvalidArgumentException('Resource has no public projection contract.');
        }
        $left = $this->normalize($static);
        $right = $this->normalize($dynamic);
        $categories = [];
        foreach (array_unique([...array_keys($left), ...array_keys($right)]) as $key) {
            if (($left[$key] ?? null) !== ($right[$key] ?? null)) {
                $categories[] = str_contains($key, 'url') || str_contains($key, 'link') ? 'link' : (str_contains($key, 'alt') ? 'accessibility' : 'content');
            }
        }
        $a = hash('sha256', json_encode($left, JSON_THROW_ON_ERROR));
        $b = hash('sha256', json_encode($right, JSON_THROW_ON_ERROR));

        return new ProjectionDiffResult($resourceKey, $a, $b, count($categories), array_values(array_unique($categories)), $ready, $now ?? CarbonImmutable::now('UTC'), hash('sha256', $resourceKey.$a.$b));
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function normalize(array $value): array
    {
        unset($value['revision_id'], $value['cache_key'], $value['csrf_token'], $value['signed_preview']);
        ksort($value);

        return $value;
    }
}
