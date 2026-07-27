<?php

namespace App\Domain\Publication\Data;

final readonly class RollbackDraftResult
{
    public function __construct(public string $resourceKey, public string $resourceId, public string $revisionId, public int $revisionNumber, public string $sourceRevisionId) {}
}
