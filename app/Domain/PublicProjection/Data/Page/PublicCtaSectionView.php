<?php

namespace App\Domain\PublicProjection\Data\Page;

final readonly class PublicCtaSectionView
{
    public function __construct(public string $heading, public string $copy, public PublicLinkView $primaryCta) {}
}
