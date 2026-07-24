<?php

namespace Database\Seeders;

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use Illuminate\Database\Seeder;

final class PermissionRoleSeeder extends Seeder
{
    public function run(): void
    {
        app(ProvisionRegisteredAccess::class)->handle();
    }
}
