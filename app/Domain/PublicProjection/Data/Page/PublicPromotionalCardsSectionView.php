<?php

namespace App\Domain\PublicProjection\Data\Page;

final readonly class PublicPromotionalCardsSectionView
{
    /** @param list<PublicPromotionalCardView> $cards */
    public function __construct(public string $heading, public array $cards) {}
}
