<?php

namespace App\Domain\Publication\Data;

final readonly class PublicationResourceDefinition
{
    /**
     * @param  list<string>  $modes
     * @param  list<string>  $permissions
     */
    public function __construct(
        public string $key,
        public string $domain,
        public string $revisionModel,
        public string $readinessEvaluator,
        public string $publicationStateOwner,
        public ?string $projectionResolver,
        public string $staticFallback,
        public string $cacheNamespace,
        public array $modes,
        public bool $emergencyUnpublish,
        public bool $preview,
        public string $sensitivity,
        public array $permissions,
        public string $status,
    ) {}
}
