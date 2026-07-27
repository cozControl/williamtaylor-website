<?php

namespace App\Domain\PublicProjection\Data;

final readonly class PublicNavigationView
{
    /** @param list<PublicNavigationItemView> $items */
    public function __construct(public array $items) {}
}
