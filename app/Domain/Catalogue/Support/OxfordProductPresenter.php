<?php

namespace App\Domain\Catalogue\Support;

use Illuminate\Support\Facades\Schema;

final class OxfordProductPresenter
{
    public const SLUG = 'the-taylor-oxford-shirt';

    public function __construct(private ProductPresenter $products) {}

    /** @return array<string, mixed>|null */
    public function resolve(): ?array
    {
        if (! Schema::hasTable('products')) {
            return null;
        }

        return $this->products->resolve(self::SLUG);
    }
}
