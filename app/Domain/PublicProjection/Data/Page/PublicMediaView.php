<?php

namespace App\Domain\PublicProjection\Data\Page;

final readonly class PublicMediaView
{
    public function __construct(public string $url, public string $alt, public bool $decorative) {}
}
