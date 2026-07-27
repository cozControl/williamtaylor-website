<?php

namespace App\Domain\Campaign\Support;

use App\Domain\Campaign\Models\Campaign;
use InvalidArgumentException;

final class CampaignMediaRoleRegistry
{
    /** @return array{singular:bool,ordered:bool,meaningful:bool,effective_alt_required:bool,maximum:int,required_for_readiness:bool} */
    public function get(string $owner, string $role): array
    {
        if ($owner !== Campaign::class || $role !== 'card') {
            throw new InvalidArgumentException('Unsupported Campaign media owner or role.');
        }

        return ['singular' => true, 'ordered' => false, 'meaningful' => true, 'effective_alt_required' => true, 'maximum' => 1, 'required_for_readiness' => true];
    }
}
