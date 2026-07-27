<?php

namespace App\Domain\PublicProjection\Data\Page;

final readonly class PublicPromotionalCardView
{
    public function __construct(public string $heading, public string $copy) {}
}
