<?php

namespace App\Domain\Catalogue\Support;

use Illuminate\Support\Facades\Validator;

final class CollectionContentSchema
{
    /** @param array<string, mixed> $input
     * @return array{title: string, short_description: string, schema_version: int}
     */
    public function normalize(array $input): array
    {
        $value = Validator::make($input, [
            'title' => ['required', 'string', 'max:255'],
            'short_description' => ['required', 'string', 'max:2000'],
        ])->validate();
        $title = trim($value['title']);
        $description = trim($value['short_description']);
        if ($title === '' || $description === '' || $title !== strip_tags($title) || $description !== strip_tags($description)) {
            throw new \InvalidArgumentException('Collection content must be non-empty plain text.');
        }

        return ['title' => $title, 'short_description' => $description, 'schema_version' => 1];
    }

    /** @param array<string, mixed> $normalized */
    public function checksum(array $normalized): string
    {
        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
