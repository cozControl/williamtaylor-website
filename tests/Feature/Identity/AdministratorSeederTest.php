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
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AdministratorSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('factory.users.administrator', [
            'name' => 'Administrator',
            'email' => 'admin@example.test',
            'password' => 'AdministratorPassword!2026',
            'role' => RoleRegistry::SUPER_ADMINISTRATOR,
        ]);
    }

    public function test_it_creates_the_registered_verified_administrator_idempotently(): void
    {
        $this->seed(AdministratorSeeder::class);

        $administrator = User::query()->where('email', 'admin@example.test')->sole();
        $passwordHash = $administrator->password;

        $this->assertSame('Administrator', $administrator->name);
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertTrue(Hash::check('AdministratorPassword!2026', $passwordHash));
        $this->assertSame([RoleRegistry::SUPER_ADMINISTRATOR], $administrator->getRoleNames()->all());
        $this->assertTrue($administrator->can(PermissionRegistry::ADMIN_ACCESS));
        $this->assertSame(56, Permission::query()->count());
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

    public function test_it_preserves_an_existing_users_identity_password_and_roles(): void
    {
        app(ProvisionRegisteredAccess::class)->handle();
        $existing = User::factory()->unverified()->create([
            'name' => 'Existing Owner',
            'email' => 'ADMIN@EXAMPLE.TEST',
            'password' => 'ExistingPassword!2026',
        ]);
        app(ControlledRoleMutation::class)->run(
            fn () => $existing->assignRole(RoleRegistry::CMS_MANAGER),
        );
        $passwordHash = $existing->password;

        $this->seed(AdministratorSeeder::class);

        $existing->refresh();
        $this->assertSame('Existing Owner', $existing->name);
        $this->assertSame('ADMIN@EXAMPLE.TEST', $existing->email);
        $this->assertSame($passwordHash, $existing->password);
        $this->assertNotNull($existing->email_verified_at);
        $this->assertEqualsCanonicalizing(
            [RoleRegistry::CMS_MANAGER, RoleRegistry::SUPER_ADMINISTRATOR],
            $existing->getRoleNames()->all(),
        );

        $verifiedAt = $existing->email_verified_at;
        $this->seed(AdministratorSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('audit_records', 1);
        $this->assertSame($passwordHash, $existing->fresh()->password);
        $this->assertTrue($verifiedAt->equalTo($existing->fresh()->email_verified_at));
    }

    public function test_missing_credentials_fail_closed_before_any_seed_write(): void
    {
        config()->set('factory.users.administrator.password', null);

        try {
            $this->seed(AdministratorSeeder::class);
            $this->fail('Administrator seeding unexpectedly accepted a missing password.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('roles', 0);
            $this->assertDatabaseCount('permissions', 0);
            $this->assertDatabaseCount('audit_records', 0);
        }
    }
}
