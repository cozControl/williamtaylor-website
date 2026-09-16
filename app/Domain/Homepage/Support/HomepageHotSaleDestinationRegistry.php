<?php

namespace App\Domain\Homepage\Support;

final class HomepageHotSaleDestinationRegistry
{
    public function __construct(private HomepageHeroDestinationRegistry $collections) {}

    /** @return array<string, string> */
    public function labels(): array
    {
        return [
            'shop_newest' => 'Shop — Newest', 'collections' => 'Collections', 'shop' => 'Shop',
            ...array_filter($this->collections->labels(), fn (string $key): bool => str_starts_with($key, 'collection:'), ARRAY_FILTER_USE_KEY),
        ];
    }

    public function url(string $destination): string
    {
        if (str_starts_with($destination, 'collection:')) {
            return $this->collections->url($destination);
        }

        return match ($destination) {
            'shop_newest' => route('products.index', ['sort' => 'newest']),
            'collections' => route('collections.index'),
            'shop' => route('products.index'),
            default => throw new \InvalidArgumentException("Unknown Hot Sale destination [{$destination}]."),
        };
    }
}
