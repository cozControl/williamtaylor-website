<?php

namespace App\Domain\PublicProjection\Data;

final readonly class PublicSocialLinkView
{
    public function __construct(
        public string $platform,
        public string $url,
        public string $label,
    ) {}
}
