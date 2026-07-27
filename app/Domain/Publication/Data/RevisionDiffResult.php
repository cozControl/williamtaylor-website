<?php

namespace App\Domain\Publication\Data;

final readonly class RevisionDiffResult
{
    /** @param list<array{category:string,path:string,before:mixed,after:mixed}> $differences */
    public function __construct(public string $resourceKey, public array $differences) {}
}
