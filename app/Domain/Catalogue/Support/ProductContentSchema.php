<?php

namespace App\Domain\Catalogue\Support;

use App\Domain\Content\Contracts\RichTextSanitizer;
use Illuminate\Support\Facades\Validator;

final class ProductContentSchema
{
    public function __construct(private RichTextSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input): array
    {
        $v = Validator::make($input, ['title' => ['required', 'string', 'max:255'], 'subtitle' => ['nullable', 'string', 'max:255'], 'short_description' => ['nullable', 'string', 'max:2000'], 'materials' => ['nullable', 'string', 'max:5000'], 'fit' => ['nullable', 'string', 'max:5000'], 'care' => ['nullable', 'string', 'max:5000'], 'features' => ['array', 'max:20'], 'features.*' => ['string', 'max:500']])->validate();
        $doc = $input['description_document'] ?? ['type' => 'doc', 'content' => []];
        $rich = $this->sanitizer->sanitize($doc);
        $trim = fn ($x) => ($s = trim((string) ($x ?? ''))) === '' ? null : $s;

        return ['title' => trim($v['title']), 'subtitle' => $trim($v['subtitle'] ?? null), 'short_description' => $trim($v['short_description'] ?? null), 'description_document' => $rich['json'], 'description_html' => $rich['html'], 'materials' => $trim($v['materials'] ?? null), 'fit' => $trim($v['fit'] ?? null), 'care' => $trim($v['care'] ?? null), 'features' => array_values(array_map('trim', $v['features'] ?? [])), 'schema_version' => 1, 'sanitizer_version' => $rich['version']];
    }

    /** @param array<string, mixed> $n */
    public function checksum(array $n): string
    {
        $copy = $n;
        unset($copy['description_html']);

        return hash('sha256', json_encode($copy, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
