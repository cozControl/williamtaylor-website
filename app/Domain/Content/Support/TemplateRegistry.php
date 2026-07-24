<?php

namespace App\Domain\Content\Support;

final class TemplateRegistry
{
    /** @return array<string, array{label: string, description: string, preview: string}> */
    public function all(): array
    {
        return [
            'standard_page' => ['label' => 'Standard page', 'description' => 'Single-column editorial content with approved structured sections.', 'preview' => 'content.preview.standard'],
            'editorial_landing' => ['label' => 'Editorial landing', 'description' => 'Responsive landing composition grounded in the supplied storefront.', 'preview' => 'content.preview.editorial-landing'],
        ];
    }

    /** @return array{label: string, description: string, preview: string} */
    public function get(string $key): array
    {
        return $this->all()[$key] ?? throw new \InvalidArgumentException('Unknown template.');
    }
}
