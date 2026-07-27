<?php

namespace App\Domain\SiteContent\Support;

final class SiteContentSchema
{
    public const VERSION = 2;

    public function __construct(private SiteContentTypeRegistry $types) {}

    /** @return array<string, mixed> */
    public function defaults(string $type): array
    {
        return $this->types->get($type)->defaults();
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validate(string $type, array $payload): array
    {
        return $this->types->get($type)->validate($payload);
    }
}
