<?php

namespace App\Domain\Merchandising\Support;

use InvalidArgumentException;

final class ProductRelationKindRegistry
{
    /** @return array{key: string, label: string, directional: bool, maximum: int, ordered: bool, reciprocal: bool, source_requires_eligibility: bool, target_requires_eligibility: bool, archive: string} */
    public function get(string $key): array
    {
        if ($key !== 'related') {
            throw new InvalidArgumentException('Unsupported Product relation kind.');
        }

        return [
            'key' => 'related', 'label' => 'Related products', 'directional' => true,
            'maximum' => 4, 'ordered' => true, 'reciprocal' => false,
            'source_requires_eligibility' => true, 'target_requires_eligibility' => true,
            'archive' => 'preserve',
        ];
    }
}
