<?php

namespace Tests\Feature\Identity;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Identity\Actions\AlignFoundationRegistry;
use App\Domain\Identity\Exceptions\UnexpectedFoundationAssignmentException;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoundationRegistryAlignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_reports_deterministic_impact_without_mutation(): void
    {
        $this->seedLegacyRegistry();

        $this->artisan('rbac:align-foundation-registry')
            ->expectsOutputToContain('Additions: pages.archive')
            ->expectsOutputToContain('Removals: (none)')
            ->expectsOutputToContain('Preview complete. No mutation was performed')
            ->assertSuccessful();

        $this->assertDatabaseMissing('permissions', ['name' => 'pages.view']);
        $this->assertDatabaseHas('permissions', ['name' => PermissionRegistry::ADMIN_ACCESS]);
        $this->assertDatabaseCount('audit_records', 0);
    }

    public function test_apply_aligns_registry_and_bundles_audits_change_resets_cache_and_is_idempotent(): void
    {
        $this->seedLegacyRegistry();
        Cache::put(config('permission.cache.key'), 'stale');

        $this->artisan('rbac:align-foundation-registry', ['--apply' => true])
            ->expectsOutputToContain('alignment was applied and audited')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            PermissionRegistry::all(),
            Permission::query()->pluck('name')->all(),
        );
        $this->assertDatabaseMissing('permissions', ['name' => 'roles.assign']);
        $this->assertSame(6, Permission::query()->where('name', 'like', 'pages.%')->count());
        $this->assertDatabaseHas('audit_records', ['action' => 'content.permission-registry.aligned']);
        $this->assertEqualsCanonicalizing(
            RoleRegistry::permissionBundles()[RoleRegistry::CMS_MANAGER],
            Role::findByName(RoleRegistry::CMS_MANAGER)->permissions->pluck('name')->all(),
        );
        $this->assertFalse(Cache::has(config('permission.cache.key')));
        $this->assertDatabaseHas('audit_records', ['action' => 'identity.permission-registry.aligned']);
        $auditCount = AuditRecord::query()->count();

        $this->artisan('rbac:align-foundation-registry', ['--apply' => true])
            ->expectsOutputToContain('already aligned')
            ->assertSuccessful();
        $this->assertSame($auditCount, AuditRecord::query()->count());
    }

    public function test_unexpected_direct_assignment_aborts_alignment_without_mutation(): void
    {
        $this->seedLegacyRegistry();
        Permission::query()->create(['name' => 'pages.publish', 'guard_name' => PermissionRegistry::GUARD]);
        $user = User::factory()->create();
        $user->givePermissionTo('pages.publish');

        $plan = app(AlignFoundationRegistry::class)->inspect();
        $this->assertSame([[
            'permission' => 'pages.publish',
            'model_type' => User::class,
            'model_identifier' => (string) $user->getKey(),
        ]], $plan->directAssignments);

        $this->expectException(UnexpectedFoundationAssignmentException::class);
        app(AlignFoundationRegistry::class)->apply('Must abort');
    }

    public function test_cms_manager_assignment_is_preserved_and_effective_permission_change_is_audited(): void
    {
        $this->seedLegacyRegistry();
        $user = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole(RoleRegistry::CMS_MANAGER));
        $beforeRoles = $user->getRoleNames()->all();

        app(AlignFoundationRegistry::class)->apply('Authorized corrective alignment');

        $this->assertSame($beforeRoles, $user->fresh()->getRoleNames()->all());
        $this->assertEqualsCanonicalizing(
            ['admin.access', 'audit.view', 'settings.view', 'media.view', 'media.upload', 'media.edit', 'media.replace', 'media.archive', 'media.restore', 'pages.view', 'pages.create', 'pages.edit', 'pages.preview', 'pages.archive', 'pages.restore'],
            $user->fresh()->getAllPermissions()->pluck('name')->all(),
        );
        $audit = AuditRecord::query()->where('action', 'identity.role-bundle.aligned')->sole();
        $this->assertNotContains('pages.view', $audit->before_summary['effective_permissions']);
        $this->assertEqualsCanonicalizing(
            ['admin.access', 'audit.view', 'settings.view', 'media.view', 'media.upload', 'media.edit', 'media.replace', 'media.archive', 'media.restore', 'pages.view', 'pages.create', 'pages.edit', 'pages.preview', 'pages.archive', 'pages.restore'],
            $audit->after_summary['effective_permissions'],
        );
    }

    private function seedLegacyRegistry(): void
    {
        foreach ($this->legacyPermissions() as $permission) {
            Permission::query()->create(['name' => $permission, 'guard_name' => PermissionRegistry::GUARD]);
        }

        $superAdministrator = Role::query()->create([
            'name' => RoleRegistry::SUPER_ADMINISTRATOR,
            'guard_name' => PermissionRegistry::GUARD,
        ]);
        $cmsManager = Role::query()->create([
            'name' => RoleRegistry::CMS_MANAGER,
            'guard_name' => PermissionRegistry::GUARD,
        ]);
        $superAdministrator->syncPermissions($this->legacyPermissions());
        $cmsManager->syncPermissions([
            'admin.access', 'audit.view', 'settings.view', 'media.view', 'media.upload',
            'media.edit', 'media.replace', 'media.archive', 'media.restore',
        ]);
    }

    /** @return list<string> */
    private function legacyPermissions(): array
    {
        return [
            'admin.access', 'users.view', 'users.manage', 'roles.view', 'roles.manage',
            'audit.view', 'audit.export', 'settings.view', 'settings.manage',
            'media.view', 'media.upload', 'media.edit', 'media.replace', 'media.archive', 'media.restore',
        ];
    }
}
