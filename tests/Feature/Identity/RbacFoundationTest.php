<?php

namespace Tests\Feature\Identity;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Identity\Actions\AssignRoleToUser;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Actions\RemoveRoleFromUser;
use App\Domain\Identity\Exceptions\FinalSuperAdministratorException;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RbacFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
    }

    public function test_registry_contains_the_approved_foundation_media_and_page_permissions(): void
    {
        $expected = [
            'admin.access', 'users.view', 'users.manage', 'roles.view', 'roles.manage',
            'audit.view', 'audit.export', 'settings.view', 'settings.manage',
            'media.view', 'media.upload', 'media.edit', 'media.replace', 'media.archive', 'media.restore',
            'inventory.view', 'inventory.manage', 'products.view', 'products.manage',
            'pages.view', 'pages.create', 'pages.edit', 'pages.preview', 'pages.archive', 'pages.restore',
            'pages.review', 'pages.approve', 'pages.publish', 'pages.schedule', 'pages.unpublish',
            'navigation.view', 'navigation.edit', 'navigation.preview', 'navigation.review', 'navigation.approve', 'navigation.publish', 'navigation.schedule', 'navigation.unpublish', 'announcements.view', 'announcements.create', 'announcements.edit', 'announcements.preview', 'announcements.review', 'announcements.approve', 'announcements.publish', 'announcements.schedule', 'announcements.unpublish', 'announcements.archive', 'announcements.restore', 'settings.preview', 'settings.review', 'settings.approve', 'settings.publish', 'settings.schedule', 'settings.unpublish',
            'campaigns.claims.review', 'campaigns.claims.approve', 'campaigns.claims.reject', 'campaigns.claims.withdraw-approval',
            'publication.emergency-unpublish',
            'orders.view', 'orders.create', 'orders.confirm', 'orders.prepare', 'orders.mark-ready', 'orders.dispatch', 'orders.deliver', 'orders.cancel', 'orders.notes.create', 'orders.payment-status.manage', 'orders.receipts.view',
        ];

        $this->assertSame($expected, PermissionRegistry::all());
        $this->assertEqualsCanonicalizing($expected, Permission::query()->pluck('name')->all());
        $this->assertCount(71, PermissionRegistry::all());
        $this->assertContains(PermissionRegistry::ADMIN_ACCESS, PermissionRegistry::all());
        $this->assertContains(PermissionRegistry::USERS_MANAGE, PermissionRegistry::all());
        $this->assertContains(PermissionRegistry::ROLES_MANAGE, PermissionRegistry::all());
        $this->assertContains(PermissionRegistry::SETTINGS_VIEW, PermissionRegistry::all());
        $this->assertContains(PermissionRegistry::SETTINGS_MANAGE, PermissionRegistry::all());
        $this->assertNotContains('roles.assign', PermissionRegistry::all());
        $this->assertNotContains('roles.revoke', PermissionRegistry::all());
        $this->assertSame(11, count(array_filter(PermissionRegistry::all(), fn (string $permission): bool => str_starts_with($permission, 'pages.'))));
        $this->assertNotContains('pages.delete', PermissionRegistry::all());
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_initial_role_bundles_are_exact_and_provisioning_is_idempotent(): void
    {
        app(ProvisionRegisteredAccess::class)->handle();
        $cmsManager = Role::findByName(RoleRegistry::CMS_MANAGER, PermissionRegistry::GUARD);

        $this->assertEqualsCanonicalizing(
            RoleRegistry::permissionBundles()[RoleRegistry::CMS_MANAGER],
            $cmsManager->permissions->pluck('name')->all(),
        );
        $this->assertFalse($cmsManager->hasPermissionTo(PermissionRegistry::USERS_MANAGE));
        $this->assertFalse($cmsManager->hasPermissionTo(PermissionRegistry::ROLES_MANAGE));
        $this->assertTrue($cmsManager->hasPermissionTo(PermissionRegistry::SETTINGS_MANAGE));
        $this->assertFalse($cmsManager->hasPermissionTo(PermissionRegistry::AUDIT_EXPORT));
        $this->artisan('rbac:audit')->assertSuccessful();
    }

    public function test_administration_access_readiness_without_creating_an_admin_route(): void
    {
        $cmsManager = User::factory()->create();
        $superAdministrator = User::factory()->create();
        $ordinaryCustomer = User::factory()->create();
        $this->grantRole($cmsManager, RoleRegistry::CMS_MANAGER);
        $this->grantRole($superAdministrator, RoleRegistry::SUPER_ADMINISTRATOR);

        $this->assertTrue(Gate::forUser($cmsManager)->allows(PermissionRegistry::ADMIN_ACCESS));
        $this->assertTrue(Gate::forUser($superAdministrator)->allows(PermissionRegistry::ADMIN_ACCESS));
        $this->assertFalse(Gate::forUser($ordinaryCustomer)->allows(PermissionRegistry::ADMIN_ACCESS));
        $this->assertFalse(Gate::allows(PermissionRegistry::ADMIN_ACCESS));

        foreach (PermissionRegistry::all() as $permission) {
            $this->assertTrue(Gate::forUser($superAdministrator)->allows($permission));
        }
    }

    public function test_users_and_roles_manage_authorize_assignment_and_the_event_is_audited(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $actor->givePermissionTo(PermissionRegistry::ROLES_MANAGE);
        $actor->givePermissionTo(PermissionRegistry::USERS_MANAGE);

        app(AssignRoleToUser::class)->handle($actor, $target, RoleRegistry::CMS_MANAGER, 'Approved staffing change');

        $this->assertTrue($target->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
        $audit = AuditRecord::query()->sole();
        $this->assertSame('identity.role.assigned', $audit->action);
        $this->assertSame(PermissionRegistry::ROLES_MANAGE, $audit->permission);
        $this->assertSame('Approved staffing change', $audit->reason);
        $this->assertContains(PermissionRegistry::ROLES_MANAGE, $audit->effective_permissions);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $audit->getKey());
    }

    public function test_users_and_roles_manage_authorize_revocation(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $actor->givePermissionTo(PermissionRegistry::ROLES_MANAGE);
        $actor->givePermissionTo(PermissionRegistry::USERS_MANAGE);
        $this->grantRole($target, RoleRegistry::CMS_MANAGER);

        app(RemoveRoleFromUser::class)->handle($actor, $target, RoleRegistry::CMS_MANAGER, 'Approved role removal');

        $this->assertFalse($target->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
        $this->assertDatabaseHas('audit_records', [
            'action' => 'identity.role.revoked',
            'permission' => PermissionRegistry::ROLES_MANAGE,
        ]);
    }

    public function test_actor_without_roles_manage_cannot_mutate_roles(): void
    {
        $this->expectException(AuthorizationException::class);

        app(AssignRoleToUser::class)->handle(
            User::factory()->create(), User::factory()->create(), RoleRegistry::CMS_MANAGER, 'Unauthorized attempt',
        );
    }

    public function test_final_super_administrator_cannot_be_removed_or_deleted(): void
    {
        $administrator = User::factory()->create();
        $this->grantRole($administrator, RoleRegistry::SUPER_ADMINISTRATOR);

        try {
            app(RemoveRoleFromUser::class)->handle(
                $administrator, $administrator, RoleRegistry::SUPER_ADMINISTRATOR, 'Unsafe self-removal',
            );
            $this->fail('Final Super Administrator removal unexpectedly succeeded.');
        } catch (FinalSuperAdministratorException) {
            $this->assertTrue($administrator->fresh()->hasRole(RoleRegistry::SUPER_ADMINISTRATOR));
        }

        $this->expectException(FinalSuperAdministratorException::class);
        $administrator->delete();
    }

    public function test_direct_role_mutation_and_append_only_audit_mutation_are_rejected(): void
    {
        $user = User::factory()->create();

        try {
            $user->assignRole(RoleRegistry::CMS_MANAGER);
            $this->fail('Direct role mutation unexpectedly succeeded.');
        } catch (LogicException) {
            $this->assertFalse($user->hasRole(RoleRegistry::CMS_MANAGER));
        }

        $actor = User::factory()->create();
        $actor->givePermissionTo(PermissionRegistry::ROLES_MANAGE);
        $actor->givePermissionTo(PermissionRegistry::USERS_MANAGE);
        app(AssignRoleToUser::class)->handle($actor, $user, RoleRegistry::CMS_MANAGER, 'Create immutable evidence');
        $audit = AuditRecord::query()->sole();

        $this->expectException(LogicException::class);
        $audit->delete();
    }

    private function grantRole(User $user, string $role): void
    {
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole($role));
    }
}
