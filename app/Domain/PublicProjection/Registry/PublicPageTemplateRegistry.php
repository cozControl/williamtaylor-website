<?php

namespace App\Domain\PublicProjection\Registry;

use InvalidArgumentException;

final class PublicPageTemplateRegistry
{
    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        if ($key !== 'about') {
            throw new InvalidArgumentException('Unknown public Page template.');
        }

        return ['type' => 'standard', 'sections' => ['hero', 'editorial_split', 'promotional_cards', 'rich_text', 'cta'], 'required' => ['hero', 'editorial_split', 'promotional_cards', 'rich_text', 'cta'], 'cardinality' => ['hero' => 1, 'editorial_split' => 1, 'promotional_cards' => 1, 'rich_text' => 1, 'cta' => 1], 'schema_version' => 1, 'fallback' => 'frontend.about-static', 'renderer' => 'frontend.about-projected'];
    }

    public function checksum(): string
    {
        return hash('sha256', json_encode($this->get('about'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
