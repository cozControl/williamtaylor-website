<?php

namespace App\Domain\Publication\Data;

final readonly class EmergencyUnpublishResult
{
    public function __construct(public string $resourceKey, public string $resourceId, public bool $transitioned, public ?string $previousRevisionId, public string $correlationId) {}
}
