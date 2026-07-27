<?php

namespace App\Domain\PublicProjection\Data;

final readonly class PublicNavigationItemView
{
    /** @param list<self> $children */
    public function __construct(
        public string $key,
        public PublicLinkView $link,
        public string $visibility,
        public array $children = [],
    ) {}
}
