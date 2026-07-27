<?php

namespace App\Domain\PublicProjection\Data;

final readonly class PublicFooterGroupView
{
    /** @param list<PublicLinkView> $links */
    public function __construct(
        public string $key,
        public string $label,
        public array $links,
    ) {}
}
