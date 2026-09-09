<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(AdministratorSeeder::class);

        if (! filter_var(config('factory.seed_enabled'), FILTER_VALIDATE_BOOL)) {
            return;
        }

        $this->call(PermissionRoleSeeder::class);
        $this->call(FactoryIdentitySeeder::class);
    }
}
