<?php

namespace App\Domain\PublicProjection\Data;

final readonly class PublicLinkView
{
    public function __construct(
        public string $label,
        public string $url,
        public bool $newTab = false,
    ) {}

    public function target(): ?string
    {
        return $this->newTab ? '_blank' : null;
    }

    public function rel(): ?string
    {
        return $this->newTab ? 'noopener noreferrer' : null;
    }
}
