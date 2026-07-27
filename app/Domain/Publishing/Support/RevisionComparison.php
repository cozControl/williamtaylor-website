<?php

namespace App\Domain\Publishing\Support;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;

final class RevisionComparison
{
    /** @return array<string, mixed> */
    public function compare(Page $page, ContentRevision $candidate, ?ContentRevision $baseline): array
    {
        if ($baseline === null) {
            return ['baseline' => null, 'metadata' => ['initial_revision'], 'sections' => []];
        }

        $metadata = [];
        $candidatePage = $candidate->payload['page'] ?? [];
        $baselinePage = $baseline->payload['page'] ?? [];
        if (($candidatePage['title'] ?? $page->title) !== ($baselinePage['title'] ?? null)) {
            $metadata[] = 'title_changed';
        }

        $before = $this->keyed($baseline->payload['sections'] ?? []);
        $after = $this->keyed($candidate->payload['sections'] ?? []);
        $changes = [];
        foreach (array_diff(array_keys($after), array_keys($before)) as $key) {
            $changes[] = ['key' => $key, 'change' => 'added', 'type' => $after[$key]['type']];
        }
        foreach (array_diff(array_keys($before), array_keys($after)) as $key) {
            $changes[] = ['key' => $key, 'change' => 'removed', 'type' => $before[$key]['type']];
        }
        foreach (array_intersect(array_keys($before), array_keys($after)) as $key) {
            $old = $before[$key];
            $new = $after[$key];
            if ($old['type'] !== $new['type']) {
                $changes[] = ['key' => $key, 'change' => 'type_changed', 'from' => $old['type'], 'to' => $new['type']];
            } elseif ($old['data'] !== $new['data']) {
                $changes[] = [
                    'key' => $key,
                    'change' => $old['type'] === 'rich_text' ? 'rich_text_changed' : 'fields_changed',
                    'type' => $old['type'],
                    'fields' => $this->changedFields($old['data'], $new['data']),
                ];
            }
        }
        if (array_keys($before) !== array_keys($after) && array_diff(array_keys($before), array_keys($after)) === [] && array_diff(array_keys($after), array_keys($before)) === []) {
            $changes[] = ['change' => 'reordered', 'keys' => array_keys($after)];
        }

        return [
            'baseline' => $baseline->getKey(),
            'metadata' => $metadata,
            'section_count' => ['before' => count($before), 'after' => count($after)],
            'sections' => $changes,
        ];
    }

    /** @param list<array<string, mixed>> $sections
     * @return array<string, array<string, mixed>>
     */
    private function keyed(array $sections): array
    {
        $result = [];
        foreach ($sections as $section) {
            if (is_string($section['key'] ?? null)) {
                $result[$section['key']] = $section;
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function changedFields(array $before, array $after): array
    {
        $fields = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $fields[] = str_contains($key, 'media') ? 'media_reference' : $key;
            }
        }
        sort($fields);

        return $fields;
    }
}
