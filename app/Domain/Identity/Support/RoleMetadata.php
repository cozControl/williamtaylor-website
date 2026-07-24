<?php

namespace App\Domain\Identity\Support;

final class RoleMetadata
{
    /** @return array<string, array{description: string, risk: string}> */
    public static function all(): array
    {
        return [
            RoleRegistry::SUPER_ADMINISTRATOR => [
                'description' => 'Full registered administrative access with a monitored authorization bypass.',
                'risk' => 'Elevated',
            ],
            RoleRegistry::CMS_MANAGER => [
                'description' => 'Draft content and media management with audit and settings review.',
                'risk' => 'Standard',
            ],
        ];
    }

    /** @return array{description: string, risk: string} */
    public static function for(string $role): array
    {
        return self::all()[$role];
    }
}
