<?php

namespace App\Domain\Publication\Data;

use Carbon\CarbonImmutable;

final readonly class ProjectionDiffResult
{
    /** @param list<string> $categories */
    public function __construct(public string $resourceKey, public string $staticChecksum, public string $dynamicChecksum, public int $differenceCount, public array $categories, public bool $ready, public CarbonImmutable $comparedAt, public string $evidenceReference) {}
}
