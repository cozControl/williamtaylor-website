<?php

namespace App\Domain\PublicProjection\Data\Page;

final readonly class PublicEditorialSplitSectionView
{
    public function __construct(public string $eyebrow, public string $heading, public string $copy, public ?PublicMediaView $media, public ?PublicLinkView $cta) {}
}
