<?php

namespace App\Domain\Homepage\Support;

final class HomepageHotSaleDestinationRegistry
{
    /** @return array<string, string> */
    public function labels(): array
    {
        return ['shop_newest' => 'Shop — Newest', 'collections' => 'Collections', 'shop' => 'Shop'];
    }

    public function url(string $destination): string
    {
        return match ($destination) {
            'shop_newest' => route('products.index', ['sort' => 'newest']),
            'collections' => route('collections.index'),
            'shop' => route('products.index'),
            default => throw new \InvalidArgumentException("Unknown Hot Sale destination [{$destination}]."),
        };
    }
}
