<?php

namespace App\Domain\PublicProjection\Data;

final readonly class PublicAnnouncementView
{
    public function __construct(
        public string $message,
        public ?PublicLinkView $cta,
        public string $variant,
        public bool $dismissible,
        public ?string $accessibilityLabel,
    ) {}
}
