<?php

namespace App\Domain\Content\Support;

final class PageTypeRegistry
{
    /** @return array<string, array{label: string, description: string, templates: list<string>, sections: list<string>, min: int, max: int, rich_text: bool, preview: string, public_renderer: string}> */
    public function all(): array
    {
        return [
            'standard' => [
                'label' => 'Standard page',
                'description' => 'A structured informational or editorial page.',
                'templates' => ['standard_page', 'about'],
                'sections' => ['hero', 'editorial_split', 'promotional_cards', 'rich_text', 'cta'],
                'min' => 1,
                'max' => 12,
                'rich_text' => true,
                'preview' => 'content.preview.standard',
                'public_renderer' => 'standard_page',
            ],
            'landing' => [
                'label' => 'Editorial landing',
                'description' => 'A campaign-style landing page without catalogue data.',
                'templates' => ['editorial_landing'],
                'sections' => ['hero', 'editorial_split', 'promotional_cards', 'rich_text', 'cta'],
                'min' => 1,
                'max' => 12,
                'rich_text' => true,
                'preview' => 'content.preview.editorial-landing',
                'public_renderer' => 'editorial_landing',
            ],
        ];
    }

    /** @return array{label: string, description: string, templates: list<string>, sections: list<string>, min: int, max: int, rich_text: bool, preview: string, public_renderer: string} */
    public function get(string $type): array
    {
        return $this->all()[$type] ?? throw new \InvalidArgumentException('Unknown page type.');
    }
}
