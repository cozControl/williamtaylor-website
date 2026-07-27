<?php

namespace App\Domain\Publication\Services;

use App\Domain\Publication\Data\RevisionDiffResult;
use App\Domain\Publication\Support\PublicationResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class RevisionDiffService
{
    public function __construct(private PublicationResourceRegistry $registry) {}

    public function compare(string $resourceKey, Model $before, Model $after): RevisionDiffResult
    {
        $definition = $this->registry->get($resourceKey);
        if (! is_a($before, $definition->revisionModel) || ! is_a($after, $definition->revisionModel)) {
            throw new InvalidArgumentException('Revision type does not match the registered resource.');
        }
        $left = $this->safe($before);
        $right = $this->safe($after);
        $differences = [];
        foreach (array_unique([...array_keys($left), ...array_keys($right)]) as $field) {
            if (($left[$field] ?? null) !== ($right[$field] ?? null)) {
                $differences[] = ['category' => array_key_exists($field, $left) && array_key_exists($field, $right) ? 'changed_field' : (array_key_exists($field, $right) ? 'added_field' : 'removed_field'), 'path' => $field, 'before' => $left[$field] ?? null, 'after' => $right[$field] ?? null];
            }
        }

        return new RevisionDiffResult($resourceKey, $differences);
    }

    /** @return array<string,mixed> */
    private function safe(Model $revision): array
    {
        return collect($revision->getAttributes())
            ->except(['id', 'created_at', 'updated_at', 'created_by', 'checksum', 'resource_id', 'resource_type'])
            ->map(fn (mixed $value) => is_string($value) && str_starts_with(trim($value), '{') ? json_decode($value, true) : $value)
            ->all();
    }
}
