<?php

namespace App\Domain\PublicProjection\Data\Page;

final readonly class PublicPageView
{
    /** @param list<object> $sections */
    public function __construct(public string $key, public string $title, public string $template, public array $sections) {}
}
