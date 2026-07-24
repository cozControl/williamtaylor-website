<?php

namespace App\Domain\Content\Support;

use RuntimeException;

final class RevisionPayload
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalize(array $payload): array
    {
        $normalized = $this->sort($payload);
        if (! is_array($normalized)) {
            throw new RuntimeException('Revision payload normalization failed.');
        }

        return $normalized;
    }

    /** @param array<string, mixed> $payload */
    public function checksum(array $payload): string
    {
        return hash('sha256', json_encode($this->normalize($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->sort($item);
        }

        return $value;
    }
}
