<?php

namespace App\Domain\PublicProjection\Data\Page;

final readonly class PublicRichTextSectionView
{
    public function __construct(public string $html) {}
}
