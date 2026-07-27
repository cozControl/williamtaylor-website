<?php

namespace App\Domain\Catalogue\Support;

use InvalidArgumentException;

final class ProductTypeRegistry
{
    /** @return array{label: string, schema_version: int, sanitizer_version: string, option_keys: list<string>, maximum_options: int, product_media_roles: list<string>, variant_media_roles: list<string>} */
    public function get(string $key): array
    {
        if ($key !== 'apparel') {
            throw new InvalidArgumentException('Unsupported Product type.');
        }

        return ['label' => 'Apparel', 'schema_version' => 1, 'sanitizer_version' => '1', 'option_keys' => ['colour', 'size'], 'maximum_options' => 2, 'product_media_roles' => ['primary', 'gallery'], 'variant_media_roles' => []];
    }

    public function checksum(): string
    {
        return hash('sha256', json_encode($this->get('apparel'), JSON_THROW_ON_ERROR));
    }
}
