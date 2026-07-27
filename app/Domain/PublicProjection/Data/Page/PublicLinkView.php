<?php

namespace App\Domain\PublicProjection\Data\Page;

final readonly class PublicLinkView
{
    public function __construct(public string $label, public string $url) {}
}
