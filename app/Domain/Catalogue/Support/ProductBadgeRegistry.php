<?php

namespace App\Domain\Catalogue\Support;

use InvalidArgumentException;

final class ProductBadgeRegistry
{
    /**
     * @return array{key: string, label: string, owner: string, assignable: bool, repeatable: bool, maximum: int, ordered: bool, readiness: bool, external_evidence: bool, semantic: string, deprecated: bool}
     */
    public function get(string $key): array
    {
        return match ($key) {
            'new' => $this->definition('new', 'New', 'analytics', 'status'),
            'limited' => $this->definition('limited', 'Limited', 'campaigns', 'campaign'),
            'pre-order' => $this->definition('pre-order', 'Pre-order', 'campaigns', 'campaign'),
            'sale' => $this->definition('sale', 'Sale', 'pricing', 'pricing'),
            'sold-out' => $this->definition('sold-out', 'Sold out', 'inventory', 'availability'),
            default => throw new InvalidArgumentException('Unsupported Product badge key.'),
        };
    }

    /** @return list<array<string, bool|int|string>> */
    public function all(): array
    {
        return array_map(fn (string $key): array => $this->get($key), ['new', 'limited', 'pre-order', 'sale', 'sold-out']);
    }

    /** @return array<string, bool|int|string> */
    public function assignable(string $key): array
    {
        $definition = $this->get($key);
        if ($definition['owner'] !== 'catalogue' || ! $definition['assignable'] || $definition['deprecated']) {
            throw new InvalidArgumentException('This badge is owned by a deferred domain.');
        }

        return $definition;
    }

    /** @return array{key: string, label: string, owner: string, assignable: false, repeatable: false, maximum: 0, ordered: true, readiness: false, external_evidence: true, semantic: string, deprecated: false} */
    private function definition(string $key, string $label, string $owner, string $semantic): array
    {
        return [
            'key' => $key, 'label' => $label, 'owner' => $owner, 'assignable' => false,
            'repeatable' => false, 'maximum' => 0, 'ordered' => true, 'readiness' => false,
            'external_evidence' => true, 'semantic' => $semantic, 'deprecated' => false,
        ];
    }
}
