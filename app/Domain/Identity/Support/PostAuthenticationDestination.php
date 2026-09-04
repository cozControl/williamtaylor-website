<?php

namespace App\Domain\Identity\Support;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

final class PostAuthenticationDestination
{
    public function for(User $user): string
    {
        return Gate::forUser($user)->allows(PermissionRegistry::ADMIN_ACCESS)
            ? route('admin.dashboard', absolute: false)
            : route('dashboard', absolute: false);
    }
}
