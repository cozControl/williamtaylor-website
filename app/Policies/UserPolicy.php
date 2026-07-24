<?php

namespace App\Policies;

use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;

final class UserPolicy
{
    public function assignRole(User $actor, User $target): bool
    {
        return $actor->can(PermissionRegistry::USERS_MANAGE)
            && $actor->can(PermissionRegistry::ROLES_MANAGE);
    }

    public function revokeRole(User $actor, User $target): bool
    {
        return $actor->can(PermissionRegistry::USERS_MANAGE)
            && $actor->can(PermissionRegistry::ROLES_MANAGE);
    }
}
