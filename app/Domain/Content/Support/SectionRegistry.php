<?php

namespace App\Domain\Content\Support;

use App\Domain\Content\Contracts\RichTextSanitizer;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SectionRegistry
{
    public function __construct(private RichTextSanitizer $richText) {}

    /** @return array<string, array{label: string, description: string, schema_version: int, max_items: int, media_roles: list<string>, pages: list<string>, preview: string}> */
    public function all(): array
    {
        return [
            'hero' => ['label' => 'Hero', 'description' => 'Primary page introduction with optional approved CTAs and media.', 'schema_version' => 1, 'max_items' => 1, 'media_roles' => ['desktop_media', 'mobile_media'], 'pages' => ['standard', 'landing'], 'preview' => 'content.sections.hero'],
            'editorial_split' => ['label' => 'Editorial split', 'description' => 'Text and media in an approved split layout.', 'schema_version' => 1, 'max_items' => 1, 'media_roles' => ['media'], 'pages' => ['standard', 'landing'], 'preview' => 'content.sections.editorial-split'],
            'promotional_cards' => ['label' => 'Promotional cards', 'description' => 'A bounded group of editorial cards.', 'schema_version' => 1, 'max_items' => 4, 'media_roles' => ['card_media'], 'pages' => ['standard', 'landing'], 'preview' => 'content.sections.promotional-cards'],
            'rich_text' => ['label' => 'Rich text', 'description' => 'Restricted semantic editorial prose.', 'schema_version' => 1, 'max_items' => 1, 'media_roles' => [], 'pages' => ['standard', 'landing'], 'preview' => 'content.sections.rich-text'],
            'cta' => ['label' => 'Call to action', 'description' => 'A bounded closing action with optional background media.', 'schema_version' => 1, 'max_items' => 1, 'media_roles' => ['background_media'], 'pages' => ['standard', 'landing'], 'preview' => 'content.sections.cta'],
        ];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<string, mixed>
     */
    public function normalize(array $section, string $pageType): array
    {
        $key = (string) ($section['key'] ?? '');
        $type = (string) ($section['type'] ?? '');
        $definition = $this->all()[$type] ?? throw new InvalidArgumentException('Unknown section type.');
        if (! Str::isUlid($key) || ! in_array($pageType, $definition['pages'], true)) {
            throw new InvalidArgumentException('Section identity or page compatibility is invalid.');
        }
        if ((int) ($section['schema_version'] ?? 0) !== $definition['schema_version']) {
            throw new InvalidArgumentException('Section schema version is unsupported.');
        }
        $data = is_array($section['data'] ?? null) ? $section['data'] : [];

        return [
            'key' => $key,
            'type' => $type,
            'schema_version' => 1,
            'data' => match ($type) {
                'hero' => $this->hero($data),
                'editorial_split' => $this->editorialSplit($data),
                'promotional_cards' => $this->cards($data),
                'rich_text' => $this->richText($data),
                'cta' => $this->cta($data),
                default => throw new InvalidArgumentException('Unknown section type.'),
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hero(array $data): array
    {
        return [
            'eyebrow' => $this->text($data, 'eyebrow', 80),
            'heading' => $this->requiredText($data, 'heading', 160),
            'copy' => $this->text($data, 'copy', 600),
            'primary_cta' => $this->link($data['primary_cta'] ?? null),
            'secondary_cta' => $this->link($data['secondary_cta'] ?? null),
            'desktop_media' => $this->media($data['desktop_media'] ?? null),
            'mobile_media' => $this->media($data['mobile_media'] ?? null),
            'alignment' => $this->enum($data, 'alignment', ['left', 'center'], 'left'),
            'variant' => $this->enum($data, 'variant', ['light', 'dark', 'overlay'], 'light'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function editorialSplit(array $data): array
    {
        return [
            'heading' => $this->requiredText($data, 'heading', 160),
            'copy' => $this->text($data, 'copy', 1200),
            'media' => $this->media($data['media'] ?? null),
            'media_position' => $this->enum($data, 'media_position', ['left', 'right'], 'left'),
            'cta' => $this->link($data['cta'] ?? null),
            'variant' => $this->enum($data, 'variant', ['plain', 'soft'], 'plain'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cards(array $data): array
    {
        $cards = is_array($data['cards'] ?? null) ? array_values($data['cards']) : [];
        if (count($cards) < 1 || count($cards) > 4) {
            throw new InvalidArgumentException('Promotional cards require between one and four cards.');
        }

        return [
            'heading' => $this->text($data, 'heading', 160),
            'cards' => array_map(fn (mixed $card): array => [
                'key' => Str::isUlid((string) (($card['key'] ?? ''))) ? (string) $card['key'] : (string) Str::ulid(),
                'heading' => $this->requiredText(is_array($card) ? $card : [], 'heading', 120),
                'copy' => $this->text(is_array($card) ? $card : [], 'copy', 300),
                'media' => $this->media(is_array($card) ? ($card['media'] ?? null) : null),
                'cta' => $this->link(is_array($card) ? ($card['cta'] ?? null) : null),
                'variant' => $this->enum(is_array($card) ? $card : [], 'variant', ['portrait', 'landscape'], 'portrait'),
            ], $cards),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function richText(array $data): array
    {
        $document = is_array($data['document'] ?? null) ? $data['document'] : ['type' => 'doc', 'content' => []];
        $projection = $this->richText->sanitize($document);

        return ['document' => $projection['json'], 'html' => $projection['html'], 'sanitizer_version' => $projection['version']];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cta(array $data): array
    {
        return [
            'heading' => $this->requiredText($data, 'heading', 160),
            'copy' => $this->text($data, 'copy', 500),
            'primary_cta' => $this->link($data['primary_cta'] ?? null, true),
            'background_media' => $this->media($data['background_media'] ?? null),
            'variant' => $this->enum($data, 'variant', ['light', 'dark'], 'light'),
        ];
    }

    /** @param array<string, mixed> $data */
    private function text(array $data, string $field, int $max): string
    {
        return trim(mb_substr((string) ($data[$field] ?? ''), 0, $max));
    }

    /** @param array<string, mixed> $data */
    private function requiredText(array $data, string $field, int $max): string
    {
        $value = $this->text($data, $field, $max);
        if ($value === '') {
            throw new InvalidArgumentException("Section field {$field} is required.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $allowed
     */
    private function enum(array $data, string $field, array $allowed, string $default): string
    {
        $value = (string) ($data[$field] ?? $default);

        return in_array($value, $allowed, true) ? $value : throw new InvalidArgumentException("Section field {$field} is invalid.");
    }

    /** @return array{kind: string, label: string, target: string}|null */
    private function link(mixed $value, bool $required = false): ?array
    {
        if (! is_array($value) || trim((string) ($value['label'] ?? '')) === '') {
            if ($required) {
                throw new InvalidArgumentException('A primary CTA is required.');
            }

            return null;
        }
        $kind = (string) ($value['kind'] ?? '');
        $target = trim((string) ($value['target'] ?? ''));
        $valid = $kind === 'internal_path'
            ? str_starts_with($target, '/') && ! str_starts_with($target, '//') && ! str_contains($target, '?') && ! str_contains($target, '#')
            : $kind === 'external_url' && filter_var($target, FILTER_VALIDATE_URL) && parse_url($target, PHP_URL_SCHEME) === 'https';
        if (! $valid) {
            throw new InvalidArgumentException('CTA links require an internal path or HTTPS URL.');
        }

        return ['kind' => $kind, 'label' => mb_substr(trim((string) $value['label']), 0, 80), 'target' => $target];
    }

    /** @return array{asset_id: string, alt_override: string|null, decorative: bool}|null */
    private function media(mixed $value): ?array
    {
        if (! is_array($value) || trim((string) ($value['asset_id'] ?? '')) === '') {
            return null;
        }

        return [
            'asset_id' => (string) $value['asset_id'],
            'alt_override' => isset($value['alt_override']) ? trim((string) $value['alt_override']) : null,
            'decorative' => (bool) ($value['decorative'] ?? false),
        ];
    }
}
