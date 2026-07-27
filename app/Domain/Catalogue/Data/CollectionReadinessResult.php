<?php

namespace App\Domain\Catalogue\Data;

final readonly class CollectionReadinessResult
{
    /** @param list<string> $failureCodes
     * @param  list<string>  $failureMessages
     */
    public function __construct(public bool $ready, public array $failureCodes, public array $failureMessages) {}
}
