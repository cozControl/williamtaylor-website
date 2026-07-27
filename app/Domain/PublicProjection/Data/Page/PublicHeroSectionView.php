<?php

namespace App\Domain\PublicProjection\Data\Page;

final readonly class PublicHeroSectionView
{
    public function __construct(public string $eyebrow, public string $heading, public string $copy, public ?PublicMediaView $media, public ?PublicLinkView $primaryCta) {}
}
