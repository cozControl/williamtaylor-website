<?php

namespace App\Domain\Identity\Support;

use JsonException;
use LogicException;

final class PreviewFingerprint
{
    /** @param array<string, mixed> $state */
    public function hash(array $state, string $registryChecksum, string $roleBundleChecksum): string
    {
        $payload = [
            'registryChecksum' => $registryChecksum,
            'roleBundleChecksum' => $roleBundleChecksum,
            'state' => $this->normalize($state),
        ];

        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            throw new LogicException('The effective-access preview could not be fingerprinted.', 0, $exception);
        }
    }

    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $normalized = array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
            usort($normalized, fn (mixed $left, mixed $right): int => serialize($left) <=> serialize($right));

            return $normalized;
        }

        ksort($value);

        return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
    }
}
