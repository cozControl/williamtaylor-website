<?php

namespace App\Domain\Content\Contracts;

interface RichTextSanitizer
{
    /**
     * @param  array<string, mixed>  $document
     * @return array{json: array<string, mixed>, html: string, version: string}
     */
    public function sanitize(array $document): array;
}
