<?php

namespace App\Domain\Campaign\Support;

use InvalidArgumentException;

final class CampaignClaimRegistry
{
    /** @return array{key:string,types:list<string>,value_type:string,required:bool,evidence_required:bool,legal_approval_required:bool,schedule_dependent:bool,display_semantics:string,permitted_before_activation:bool,invalidated_by_targets:bool,maximum:int,deprecated:bool} */
    public function get(string $type, string $key): array
    {
        if (! in_array($type, ['pre_order', 'limited_edition'], true)) {
            throw new InvalidArgumentException('Unsupported Campaign claim.');
        }

        return match ($key) {
            'public_window_statement' => ['key' => $key, 'types' => ['pre_order', 'limited_edition'], 'value_type' => 'plain_text', 'required' => true, 'evidence_required' => true, 'legal_approval_required' => true, 'schedule_dependent' => true, 'display_semantics' => 'campaign availability period', 'permitted_before_activation' => true, 'invalidated_by_targets' => false, 'maximum' => 300, 'deprecated' => false],
            'edition_statement' => $type === 'limited_edition'
                ? ['key' => $key, 'types' => ['limited_edition'], 'value_type' => 'plain_text', 'required' => true, 'evidence_required' => true, 'legal_approval_required' => true, 'schedule_dependent' => false, 'display_semantics' => 'fixed planned edition statement', 'permitted_before_activation' => true, 'invalidated_by_targets' => true, 'maximum' => 120, 'deprecated' => false]
                : throw new InvalidArgumentException('Unsupported Campaign claim.'),
            default => throw new InvalidArgumentException('Unsupported Campaign claim.'),
        };
    }

    /** @return array{value:string,evidence_reference:string,evidence_summary:string,checksum:string} */
    public function normalize(string $type, string $key, string $value, string $reference, string $summary): array
    {
        $definition = $this->get($type, $key);
        $value = trim($value);
        $reference = trim($reference);
        $summary = trim($summary);
        if ($value === '' || mb_strlen($value) > $definition['maximum'] || $value !== strip_tags($value) || $reference === '' || mb_strlen($reference) > 500 || preg_match('/^(?:[a-z]:\\\\|\\/|file:)/i', $reference) || $summary === '' || mb_strlen($summary) > 500) {
            throw new InvalidArgumentException('Claim value and bounded non-local evidence are required.');
        }

        return ['value' => $value, 'evidence_reference' => $reference, 'evidence_summary' => $summary, 'checksum' => hash('sha256', json_encode([$key, $value, $reference, $summary], JSON_THROW_ON_ERROR))];
    }
}
