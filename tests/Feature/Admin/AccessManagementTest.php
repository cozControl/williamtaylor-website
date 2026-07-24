<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Identity\Actions\AssignRoleToUser;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Actions\RemoveRoleFromUser;
use App\Domain\Identity\Exceptions\RoleMutationReasonRequiredException;
use App\Domain\Identity\Exceptions\SelfLockoutException;
use App\Domain\Identity\Queries\EffectiveUserAccessQuery;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Livewire\Admin\Access\UserAccessDetail;
use App\Livewire\Admin\Access\UsersIndex;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
    }

    public function test_users_and_roles_routes_enforce_full_view_boundaries(): void
    {
        $target = User::factory()->create();
        $cms = User::factory()->create();
        $super = User::factory()->create();
        $this->grantRole($cms, RoleRegistry::CMS_MANAGER);
        $this->grantRole($super, RoleRegistry::SUPER_ADMINISTRATOR);

        $urls = [
            route('admin.access.users.index'),
            route('admin.access.users.show', $target),
            route('admin.access.roles.index'),
            route('admin.access.roles.show', ['role' => RoleRegistry::CMS_MANAGER]),
        ];
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
        foreach ($urls as $url) {
            $this->actingAs($cms)->get($url)->assertForbidden();
        }
        foreach ($urls as $url) {
            $this->actingAs($super)->get($url)->assertOk();
        }

        $this->actingAs($super)
            ->get(route('admin.access.roles.show', ['role' => 'Unknown Role']))
            ->assertNotFound()
            ->assertDontSee('Spatie');
    }

    public function test_user_search_trims_and_treats_wildcards_as_literals(): void
    {
        $admin = $this->superAdministrator();
        User::factory()->create(['name' => 'Alice Percent', 'email' => 'alice@example.test']);
        User::factory()->create(['name' => 'Literal % User', 'email' => 'literal@example.test']);
        User::factory()->create(['name' => 'Other Person', 'email' => 'other@example.test']);

        $this->actingAs($admin);
        Livewire::test(UsersIndex::class)->set('search', '  Alice  ')->assertSee('Alice Percent')->assertDontSee('Other Person');
        Livewire::test(UsersIndex::class)->set('search', 'example.test')->assertSee('alice@example.test')->assertSee('other@example.test');
        Livewire::test(UsersIndex::class)->set('search', '%')->assertSee('Literal % User')->assertDontSee('Alice Percent');
    }

    public function test_user_filters_sort_allowlist_and_pagination_are_scoped(): void
    {
        $admin = $this->superAdministrator();
        $cms = User::factory()->create(['name' => 'CMS Assigned']);
        $unverified = User::factory()->unverified()->create(['name' => 'Unverified Customer']);
        $this->grantRole($cms, RoleRegistry::CMS_MANAGER);
        User::factory()->count(16)->create();

        $this->actingAs($admin);
        $roleResults = Livewire::test(UsersIndex::class)->set('role', RoleRegistry::CMS_MANAGER)->instance()->users();
        $this->assertSame(['CMS Assigned'], collect($roleResults->items())->pluck('name')->all());

        $verificationResults = Livewire::test(UsersIndex::class)->set('verification', 'unverified')->instance()->users();
        $this->assertSame(['Unverified Customer'], collect($verificationResults->items())->pluck('name')->all());

        $adminResults = Livewire::test(UsersIndex::class)->set('administrativeAccess', 'has')->instance()->users();
        $this->assertContains('CMS Assigned', collect($adminResults->items())->pluck('name')->all());
        $this->assertNotContains('Unverified Customer', collect($adminResults->items())->pluck('name')->all());

        $customerResults = Livewire::test(UsersIndex::class)->set('administrativeAccess', 'missing')->instance()->users();
        $this->assertGreaterThan(0, $customerResults->total());
        $this->assertNotContains('CMS Assigned', collect($customerResults->items())->pluck('name')->all());
        Livewire::test(UsersIndex::class)->set('sort', 'password')->assertSee('Users');

        Livewire::test(UsersIndex::class)->set('role', RoleRegistry::CMS_MANAGER)
            ->call('gotoPage', 2)
            ->assertSet('role', RoleRegistry::CMS_MANAGER);
    }

    public function test_effective_access_identifies_sources_duplicates_bypass_and_drift(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, RoleRegistry::SUPER_ADMINISTRATOR);
        $this->grantRole($user, RoleRegistry::CMS_MANAGER);
        $user->givePermissionTo(PermissionRegistry::ADMIN_ACCESS);
        $unknown = Permission::create(['name' => 'unknown.permission', 'guard_name' => PermissionRegistry::GUARD]);
        $user->givePermissionTo($unknown);

        $access = app(EffectiveUserAccessQuery::class)->for($user);

        $this->assertTrue($access->usesSuperAdministratorBypass);
        $this->assertTrue($access->hasAdministrativeAccess);
        $this->assertContains(PermissionRegistry::ADMIN_ACCESS, $access->duplicateGrants);
        $this->assertContains('Super Administrator bypass', $access->permissionSources[PermissionRegistry::ADMIN_ACCESS]);
        $this->assertStringContainsString('Unknown direct permission', implode(' ', $access->warnings));
        $this->assertStringContainsString('Direct registered permission', implode(' ', $access->warnings));
    }

    public function test_role_catalogue_is_registered_read_only_and_reports_drift(): void
    {
        $admin = $this->superAdministrator();
        $role = Role::findByName(RoleRegistry::CMS_MANAGER, PermissionRegistry::GUARD);
        $role->revokePermissionTo(PermissionRegistry::SETTINGS_VIEW);

        $response = $this->actingAs($admin)->get(route('admin.access.roles.show', ['role' => RoleRegistry::CMS_MANAGER]));
        $response->assertOk()
            ->assertSee('Role registry drift detected')
            ->assertSee('php artisan rbac:audit')
            ->assertDontSee('data-admin-mutation', false)
            ->assertDontSee('Save');
    }

    public function test_authorized_livewire_assignment_and_revocation_use_existing_audited_actions(): void
    {
        $admin = $this->superAdministrator();
        $target = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(UserAccessDetail::class, ['userId' => $target->getKey()])
            ->set('operation', 'assign')->set('selectedRole', RoleRegistry::CMS_MANAGER)->set('reason', 'Approved access duty')
            ->call('previewChange')
            ->assertSet('preview.willHaveAdministrativeAccess', true)
            ->set('confirmed', true)->call('applyChange')
            ->assertSee('Access was updated');
        $this->assertTrue($target->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
        $this->assertDatabaseHas('audit_records', ['action' => 'identity.role.assigned', 'reason' => 'Approved access duty']);

        Livewire::test(UserAccessDetail::class, ['userId' => $target->getKey()])
            ->set('operation', 'revoke')->set('selectedRole', RoleRegistry::CMS_MANAGER)->set('reason', 'Approved access removal')
            ->call('previewChange')->assertSet('preview.willHaveAdministrativeAccess', false)
            ->set('confirmed', true)->call('applyChange');
        $this->assertFalse($target->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
        $this->assertDatabaseHas('audit_records', ['action' => 'identity.role.revoked', 'reason' => 'Approved access removal']);
        $this->assertDatabaseCount('audit_records', 2);
    }

    public function test_mutations_require_both_permissions_reason_registered_role_and_confirmation(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $actor->givePermissionTo(PermissionRegistry::ROLES_MANAGE);

        $this->expectException(AuthorizationException::class);
        app(AssignRoleToUser::class)->handle($actor, $target, RoleRegistry::CMS_MANAGER, 'Only one permission');
    }

    public function test_actions_require_a_reason_and_audit_failure_rolls_back(): void
    {
        $admin = $this->superAdministrator();
        $target = User::factory()->create();

        try {
            app(AssignRoleToUser::class)->handle($admin, $target, RoleRegistry::CMS_MANAGER, '   ');
            $this->fail('Empty reason unexpectedly accepted.');
        } catch (RoleMutationReasonRequiredException) {
            $this->assertFalse($target->hasRole(RoleRegistry::CMS_MANAGER));
        }

        AuditRecord::creating(fn () => throw new RuntimeException('Audit unavailable'));

        try {
            app(AssignRoleToUser::class)->handle($admin, $target, RoleRegistry::CMS_MANAGER, 'Must roll back');
            $this->fail('Audit failure unexpectedly committed.');
        } catch (RuntimeException) {
            $this->assertFalse($target->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
            $this->assertDatabaseCount('audit_records', 0);
        }
    }

    public function test_preview_is_recomputed_and_assignment_is_idempotent(): void
    {
        $admin = $this->superAdministrator();
        $target = User::factory()->create();
        $preview = app(EffectiveUserAccessQuery::class)->preview($target, [RoleRegistry::CMS_MANAGER]);
        $this->assertContains(PermissionRegistry::ADMIN_ACCESS, $preview->gainedPermissions);

        app(AssignRoleToUser::class)->handle($admin, $target, RoleRegistry::CMS_MANAGER, 'First assignment');
        app(AssignRoleToUser::class)->handle($admin, $target, RoleRegistry::CMS_MANAGER, 'Repeated assignment');

        $this->assertDatabaseCount('audit_records', 1);
        $this->assertTrue($target->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
    }

    public function test_final_admin_and_self_lockout_safeguards_preserve_state(): void
    {
        $admin = $this->superAdministrator();

        Livewire::actingAs($admin)
            ->test(UserAccessDetail::class, ['userId' => $admin->getKey()])
            ->set('operation', 'revoke')->set('selectedRole', RoleRegistry::SUPER_ADMINISTRATOR)->set('reason', 'Unsafe')
            ->call('previewChange')->set('confirmed', true)->call('applyChange')
            ->assertSee('blocked because it would violate');
        $this->assertTrue($admin->fresh()->hasRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->assertDatabaseCount('audit_records', 0);

        $otherAdmin = $this->superAdministrator();
        $this->grantRole($admin, RoleRegistry::CMS_MANAGER);
        app(RemoveRoleFromUser::class)->handle($admin, $admin, RoleRegistry::SUPER_ADMINISTRATOR, 'Retain CMS access');
        $this->assertFalse($admin->fresh()->hasRole(RoleRegistry::SUPER_ADMINISTRATOR));
        $this->assertTrue($admin->fresh()->hasRole(RoleRegistry::CMS_MANAGER));

        $this->expectException(SelfLockoutException::class);
        app(RemoveRoleFromUser::class)->handle($admin, $admin, RoleRegistry::CMS_MANAGER, 'Would lose all admin access');
    }

    public function test_sensitive_authentication_values_and_unapproved_routes_are_absent(): void
    {
        $admin = $this->superAdministrator();
        $target = User::factory()->withTwoFactor()->create(['name' => '<script>unsafe</script>']);
        $response = $this->actingAs($admin)->get(route('admin.access.users.show', $target));
        $response->assertOk()
            ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)
            ->assertDontSee($target->password)
            ->assertDontSee((string) $target->two_factor_secret)
            ->assertDontSee('recovery-code')
            ->assertDontSee('<script>unsafe</script>', false);

        foreach (['admin.users.create', 'admin.users.edit', 'admin.roles.create', 'admin.roles.edit', 'admin.permissions.index'] as $route) {
            $this->assertFalse(Route::has($route));
        }
    }

    public function test_users_and_roles_pages_stay_within_query_budgets(): void
    {
        $admin = $this->superAdministrator();
        User::factory()->count(15)->create();
        $this->actingAs($admin);

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->get(route('admin.access.users.index'))->assertOk();
        $this->assertLessThanOrEqual(20, $queries);

        $beforeRoles = $queries;
        $this->get(route('admin.access.roles.index'))->assertOk();
        $this->assertLessThanOrEqual(15, $queries - $beforeRoles);
    }

    private function superAdministrator(): User
    {
        $user = User::factory()->create();
        $this->grantRole($user, RoleRegistry::SUPER_ADMINISTRATOR);

        return $user;
    }

    private function grantRole(User $user, string $role): void
    {
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole($role));
    }
}
