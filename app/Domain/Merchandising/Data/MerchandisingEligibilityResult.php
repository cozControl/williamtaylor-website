<?php

namespace App\Domain\Merchandising\Data;

final readonly class MerchandisingEligibilityResult
{
    /** @param list<string> $failureCodes */
    public function __construct(public bool $eligible, public array $failureCodes) {}
}
