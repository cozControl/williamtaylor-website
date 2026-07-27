<?php

namespace App\Domain\Publishing\Events;

final readonly class PageRevisionPublished
{
    public function __construct(public string $pageId, public string $revisionId) {}
}
