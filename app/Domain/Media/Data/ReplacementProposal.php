<?php

namespace App\Domain\Media\Data;

final readonly class ReplacementProposal
{
    /**
     * @param  array<string, mixed>  $currentFacts
     * @param  array<string, mixed>  $proposedFacts
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $token,
        public string $fingerprint,
        public array $currentFacts,
        public array $proposedFacts,
        public array $warnings,
        public int $usageCount,
    ) {}
}
