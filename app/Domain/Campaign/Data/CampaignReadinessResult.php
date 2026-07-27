<?php

namespace App\Domain\Campaign\Data;

final readonly class CampaignReadinessResult
{
    /** @param list<string> $failureCodes */
    public function __construct(public bool $ready, public array $failureCodes) {}
}
