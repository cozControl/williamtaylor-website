<?php

namespace App\Domain\Campaign\Support;

use InvalidArgumentException;

final class CampaignTypeRegistry
{
    /** @return array{key:string,label:string,badge_key:string,target_types:list<string>,ordered:bool,minimum:int,maximum:int,schedule_required:bool,end_required:bool,media_required:bool,allowed_claims:list<string>,required_claims:list<string>,legal_approval_required:bool,public_projection:bool,deprecated:bool} */
    public function get(string $key): array
    {
        return match ($key) {
            'pre_order' => $this->definition('pre_order', 'Pre-Order', 'pre-order', 20),
            'limited_edition' => $this->definition('limited_edition', 'Limited Edition', 'limited', 20),
            default => throw new InvalidArgumentException('Unsupported Campaign type.'),
        };
    }

    /** @return array{key:string,label:string,badge_key:string,target_types:list<string>,ordered:bool,minimum:int,maximum:int,schedule_required:bool,end_required:bool,media_required:bool,allowed_claims:list<string>,required_claims:list<string>,legal_approval_required:bool,public_projection:bool,deprecated:bool} */
    private function definition(string $key, string $label, string $badge, int $maximum): array
    {
        return ['key' => $key, 'label' => $label, 'badge_key' => $badge, 'target_types' => ['product'], 'ordered' => true, 'minimum' => 1, 'maximum' => $maximum, 'schedule_required' => true, 'end_required' => true, 'media_required' => true, 'allowed_claims' => ['public_window_statement'], 'required_claims' => ['public_window_statement'], 'legal_approval_required' => true, 'public_projection' => true, 'deprecated' => false];
    }
}
