<?php

namespace Tests\Feature\Identity;

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Database\Seeders\AdministratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdministratorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_registered_verified_administrator_idempotently(): void
    {
        $this->seed(AdministratorSeeder::class);

        $administrator = User::query()->where('email', 'admin@example.com')->sole();
        $passwordHash = $administrator->password;

        $this->assertSame('Administrator', $administrator->name);
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertTrue(Hash::check('password123!@', $passwordHash));
        $this->assertSame([RoleRegistry::SUPER_ADMINISTRATOR], $administrator->getRoleNames()->all());
        $this->assertTrue($administrator->can(PermissionRegistry::ADMIN_ACCESS));
        $this->assertSame(count(PermissionRegistry::all()), Permission::query()->count());
        $this->assertSame(4, Role::query()->count());
        $this->assertDatabaseCount('audit_records', 1);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
        $this->assertDatabaseCount('collections', 0);
        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('campaign_claims', 0);

        $this->seed(AdministratorSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_records', 1);
        $this->assertSame($passwordHash, $administrator->fresh()->password);
    }

    public function test_it_reconciles_an_existing_administrator_identity_and_preserves_additional_roles(): void
    {
        app(ProvisionRegisteredAccess::class)->handle();
        $existing = User::factory()->unverified()->create([
            'name' => 'Existing Owner',
            'email' => 'ADMIN@EXAMPLE.COM',
            'password' => 'ExistingPassword!2026',
        ]);
        app(ControlledRoleMutation::class)->run(
            fn () => $existing->assignRole(RoleRegistry::CMS_MANAGER),
        );
        $this->seed(AdministratorSeeder::class);

        $existing->refresh();
        $this->assertSame('Administrator', $existing->name);
        $this->assertSame('admin@example.com', $existing->email);
        $this->assertTrue(Hash::check('password123!@', $existing->password));
        $this->assertNotNull($existing->email_verified_at);
        $this->assertEqualsCanonicalizing(
            [RoleRegistry::CMS_MANAGER, RoleRegistry::SUPER_ADMINISTRATOR],
            $existing->getRoleNames()->all(),
        );

        $verifiedAt = $existing->email_verified_at;
        $this->seed(AdministratorSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_records', 1);
        $this->assertTrue(Hash::check('password123!@', $existing->fresh()->password));
        $this->assertTrue($verifiedAt->equalTo($existing->fresh()->email_verified_at));
    }

    public function test_it_does_not_depend_on_factory_administrator_configuration(): void
    {
        config()->set('factory.users.administrator', null);

        $this->seed(AdministratorSeeder::class);

        $administrator = User::query()->where('email', 'admin@example.com')->sole();
        $this->assertTrue(Hash::check('password123!@', $administrator->password));
        $this->assertTrue($administrator->hasRole(RoleRegistry::SUPER_ADMINISTRATOR));
    }
}
