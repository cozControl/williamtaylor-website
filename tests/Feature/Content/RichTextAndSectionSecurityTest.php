<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Contracts\RichTextSanitizer;
use App\Domain\Content\Support\PageTypeRegistry;
use App\Domain\Content\Support\SectionRegistry;
use App\Domain\Content\Support\TemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

final class RichTextAndSectionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registries_are_code_owned_and_only_approved_values_exist(): void
    {
        $this->assertSame(['standard', 'landing'], array_keys(app(PageTypeRegistry::class)->all()));
        $this->assertSame(['standard_page', 'editorial_landing'], array_keys(app(TemplateRegistry::class)->all()));
        $this->assertSame(['hero', 'editorial_split', 'promotional_cards', 'rich_text', 'cta'], array_keys(app(SectionRegistry::class)->all()));
    }

    public function test_approved_tiptap_json_produces_sanitized_semantic_html(): void
    {
        $result = app(RichTextSanitizer::class)->sanitize(['type' => 'doc', 'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Story']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Read more', 'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => '/story']]]]]],
        ]]);
        $this->assertStringContainsString('<h2>Story</h2>', $result['html']);
        $this->assertStringContainsString('href="/story"', $result['html']);
        $this->assertStringNotContainsString('class=', $result['html']);
        $this->assertSame('1', $result['version']);
    }

    public function test_h1_unsupported_nodes_marks_and_unsafe_links_fail_server_side(): void
    {
        $invalid = [
            json_decode('{"type":"doc","content":[{"type":"heading","attrs":{"level":1},"content":[]}]}', true, 512, JSON_THROW_ON_ERROR),
            json_decode('{"type":"doc","content":[{"type":"image","attrs":{"src":"data:image/png;base64,AA"}}]}', true, 512, JSON_THROW_ON_ERROR),
            json_decode('{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"x","marks":[{"type":"underline"}]}]}]}', true, 512, JSON_THROW_ON_ERROR),
            json_decode('{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"x","marks":[{"type":"link","attrs":{"href":"javascript:alert(1)"}}]}]}]}', true, 512, JSON_THROW_ON_ERROR),
        ];
        foreach ($invalid as $document) {
            try {
                app(RichTextSanitizer::class)->sanitize($document);
                $this->fail('Unsafe rich text unexpectedly passed.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_plain_text_is_escaped_and_arbitrary_section_fields_are_discarded(): void
    {
        $section = app(SectionRegistry::class)->normalize([
            'key' => (string) Str::ulid(),
            'type' => 'hero',
            'schema_version' => 1,
            'data' => ['heading' => '<script>alert(1)</script>', 'class' => 'evil', 'javascript' => 'alert(1)'],
        ], 'standard');
        $this->assertSame('<script>alert(1)</script>', $section['data']['heading']);
        $this->assertArrayNotHasKey('class', $section['data']);
        $this->assertArrayNotHasKey('javascript', $section['data']);
    }

    public function test_unknown_section_duplicate_schema_and_unsafe_cta_fail(): void
    {
        foreach ([
            ['key' => (string) Str::ulid(), 'type' => 'custom_html', 'schema_version' => 1, 'data' => []],
            ['key' => (string) Str::ulid(), 'type' => 'hero', 'schema_version' => 2, 'data' => ['heading' => 'x']],
            ['key' => (string) Str::ulid(), 'type' => 'cta', 'schema_version' => 1, 'data' => ['heading' => 'x', 'primary_cta' => ['kind' => 'external_url', 'label' => 'Bad', 'target' => 'http://example.com']]],
        ] as $section) {
            try {
                app(SectionRegistry::class)->normalize($section, 'standard');
                $this->fail('Invalid section unexpectedly passed.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_promotional_card_count_is_bounded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(SectionRegistry::class)->normalize([
            'key' => (string) Str::ulid(),
            'type' => 'promotional_cards',
            'schema_version' => 1,
            'data' => ['cards' => array_fill(0, 5, ['heading' => 'Card'])],
        ], 'landing');
    }
}
