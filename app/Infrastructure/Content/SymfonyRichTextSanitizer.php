<?php

namespace App\Infrastructure\Content;

use App\Domain\Content\Contracts\RichTextSanitizer;
use InvalidArgumentException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class SymfonyRichTextSanitizer implements RichTextSanitizer
{
    private const MAX_DOCUMENT_BYTES = 200000;

    private const MAX_DEPTH = 32;

    private const MAX_NODES = 2000;

    private const MAX_TEXT_LENGTH = 20000;

    private const NODES = ['doc', 'paragraph', 'heading', 'bulletList', 'orderedList', 'listItem', 'blockquote', 'text'];

    private const MARKS = ['bold', 'italic', 'link'];

    public function sanitize(array $document): array
    {
        $encoded = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (strlen($encoded) > self::MAX_DOCUMENT_BYTES) {
            throw new InvalidArgumentException('Rich text document exceeds the allowed size.');
        }

        $nodeCount = 0;
        $normalized = $this->node($document, true, 0, $nodeCount);
        $html = $this->render($normalized);
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('a', ['href', 'rel', 'target'])
            ->allowLinkSchemes(['https'])
            ->allowRelativeLinks();

        return ['json' => $normalized, 'html' => (new HtmlSanitizer($config))->sanitize($html), 'version' => '1'];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function node(array $node, bool $root, int $depth, int &$nodeCount): array
    {
        $nodeCount++;
        if ($depth > self::MAX_DEPTH || $nodeCount > self::MAX_NODES) {
            throw new InvalidArgumentException('Rich text document exceeds the structural limits.');
        }

        $type = $node['type'] ?? null;
        if (! is_string($type) || ! in_array($type, self::NODES, true) || ($root && $type !== 'doc')) {
            throw new InvalidArgumentException('Rich text contains an unsupported node.');
        }
        if ($type === 'heading' && ! in_array($node['attrs']['level'] ?? null, [2, 3, 4], true)) {
            throw new InvalidArgumentException('Rich text headings are limited to H2 through H4.');
        }
        $normalized = ['type' => $type];
        if ($type === 'heading') {
            $normalized['attrs'] = ['level' => (int) $node['attrs']['level']];
        }
        if ($type === 'text') {
            $text = (string) ($node['text'] ?? '');
            if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                throw new InvalidArgumentException('Rich text node exceeds the allowed text length.');
            }
            $normalized['text'] = $text;
            $normalized['marks'] = $this->marks(is_array($node['marks'] ?? null) ? $node['marks'] : []);
        } else {
            $content = is_array($node['content'] ?? null) ? $node['content'] : [];
            $normalized['content'] = [];
            foreach (array_values($content) as $child) {
                $normalized['content'][] = $this->node(is_array($child) ? $child : [], false, $depth + 1, $nodeCount);
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $marks
     * @return list<array<string, mixed>>
     */
    private function marks(array $marks): array
    {
        $result = [];
        foreach ($marks as $mark) {
            if (! is_array($mark) || ! in_array($mark['type'] ?? null, self::MARKS, true)) {
                throw new InvalidArgumentException('Rich text contains an unsupported mark.');
            }
            if ($mark['type'] === 'link') {
                $href = (string) ($mark['attrs']['href'] ?? '');
                if (! str_starts_with($href, '/') && filter_var($href, FILTER_VALIDATE_URL) === false) {
                    throw new InvalidArgumentException('Rich text contains an invalid link.');
                }
                if (str_starts_with($href, '//') || (! str_starts_with($href, '/') && parse_url($href, PHP_URL_SCHEME) !== 'https')) {
                    throw new InvalidArgumentException('Rich text links must use an internal path or HTTPS.');
                }
                $result[] = ['type' => 'link', 'attrs' => ['href' => $href]];
            } else {
                $result[] = ['type' => $mark['type']];
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $node */
    private function render(array $node): string
    {
        if ($node['type'] === 'text') {
            $html = e((string) $node['text']);
            foreach ($node['marks'] ?? [] as $mark) {
                $html = match ($mark['type']) {
                    'bold' => '<strong>'.$html.'</strong>',
                    'italic' => '<em>'.$html.'</em>',
                    'link' => '<a href="'.e($mark['attrs']['href']).'" rel="noopener noreferrer">'.$html.'</a>',
                    default => throw new InvalidArgumentException('Rich text contains an unsupported mark.'),
                };
            }

            return $html;
        }
        $content = implode('', array_map(fn (array $child): string => $this->render($child), $node['content'] ?? []));

        return match ($node['type']) {
            'doc' => $content,
            'paragraph' => '<p>'.$content.'</p>',
            'heading' => '<h'.$node['attrs']['level'].'>'.$content.'</h'.$node['attrs']['level'].'>',
            'bulletList' => '<ul>'.$content.'</ul>',
            'orderedList' => '<ol>'.$content.'</ol>',
            'listItem' => '<li>'.$content.'</li>',
            'blockquote' => '<blockquote>'.$content.'</blockquote>',
            default => throw new InvalidArgumentException('Rich text contains an unsupported node.'),
        };
    }
}
