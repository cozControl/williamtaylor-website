<?php

namespace App\Domain\Homepage\Support;

final class HomepageHeroDestinationRegistry
{
    /** @return array<string, string> */
    public function labels(): array
    {
        return ['new_arrivals' => 'New Arrivals', 'collections' => 'Collections'];
    }

    public function url(string $destination): string
    {
        return match ($destination) {
            'new_arrivals' => route('products.index', ['sort' => 'newest']),
            'collections' => route('collections.index'),
            default => throw new \InvalidArgumentException("Unknown Homepage Hero destination [{$destination}]."),
        };
    }
}
