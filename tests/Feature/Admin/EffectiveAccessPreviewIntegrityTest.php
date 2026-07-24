<?php

namespace Tests\Feature\Admin;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Queries\EffectiveUserAccessQuery;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\PreviewFingerprint;
use App\Domain\Identity\Support\RoleRegistry;
use App\Livewire\Admin\Access\UserAccessDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EffectiveAccessPreviewIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
    }

    public function test_preview_retains_registered_direct_permissions_and_excludes_unknown_permissions(): void
    {
        $target = User::factory()->create();
        $target->givePermissionTo(PermissionRegistry::ADMIN_ACCESS);
        $target->givePermissionTo(PermissionRegistry::AUDIT_VIEW);
        $unknown = Permission::create([
            'name' => 'legacy.unknown',
            'guard_name' => PermissionRegistry::GUARD,
        ]);
        $target->givePermissionTo($unknown);

        $preview = app(EffectiveUserAccessQuery::class)->preview($target, [RoleRegistry::CMS_MANAGER]);

        $this->assertContains(PermissionRegistry::ADMIN_ACCESS, $preview->proposedEffectivePermissions);
        $this->assertContains(PermissionRegistry::AUDIT_VIEW, $preview->proposedEffectivePermissions);
        $this->assertNotContains('legacy.unknown', $preview->proposedEffectivePermissions);
        $this->assertContains(PermissionRegistry::ADMIN_ACCESS, $preview->duplicateGrants);
        $this->assertSame(
            ['Direct grant (registry warning)', 'Role: CMS Manager'],
            $preview->proposedPermissionSources[PermissionRegistry::ADMIN_ACCESS],
        );
        $this->assertTrue($preview->willHaveAdministrativeAccess);
        $this->assertStringContainsString('Unknown direct permission', implode(' ', $preview->warnings));
        $this->assertSame($preview->gainedPermissions, collect($preview->gainedPermissions)->sort()->values()->all());
        $this->assertSame($preview->lostPermissions, collect($preview->lostPermissions)->sort()->values()->all());
    }

    public function test_role_removal_retains_direct_access_and_bypass_sources_are_distinct(): void
    {
        $target = User::factory()->create();
        $this->grantRole($target, RoleRegistry::SUPER_ADMINISTRATOR);
        $target->givePermissionTo(PermissionRegistry::ADMIN_ACCESS);

        $withBypass = app(EffectiveUserAccessQuery::class)->preview(
            $target,
            [RoleRegistry::SUPER_ADMINISTRATOR],
        );
        $this->assertContains(PermissionRegistry::ADMIN_ACCESS, $withBypass->duplicateGrants);
        $this->assertContains(
            'Super Administrator bypass',
            $withBypass->proposedPermissionSources[PermissionRegistry::ADMIN_ACCESS],
        );

        $withoutBypass = app(EffectiveUserAccessQuery::class)->preview($target, []);
        $this->assertFalse($withoutBypass->willBeSuperAdministrator);
        $this->assertTrue($withoutBypass->willHaveAdministrativeAccess);
        $this->assertFalse($withoutBypass->administrativeAccessChanges);
        $this->assertSame(
            ['Direct grant (registry warning)'],
            $withoutBypass->proposedPermissionSources[PermissionRegistry::ADMIN_ACCESS],
        );
        $this->assertNotContains(PermissionRegistry::ADMIN_ACCESS, $withoutBypass->lostPermissions);
    }

    public function test_fingerprint_is_order_independent_and_changes_with_security_versions(): void
    {
        $service = app(PreviewFingerprint::class);
        $first = $service->hash(
            ['roles' => ['B', 'A'], 'sources' => ['x' => ['Direct', 'Role']]],
            'registry-v1',
            'roles-v1',
        );
        $reordered = $service->hash(
            ['sources' => ['x' => ['Role', 'Direct']], 'roles' => ['A', 'B']],
            'registry-v1',
            'roles-v1',
        );

        $this->assertSame($first, $reordered);
        $this->assertNotSame($first, $service->hash(['roles' => ['A']], 'registry-v1', 'roles-v1'));
        $this->assertNotSame($first, $service->hash(
            ['roles' => ['B', 'A'], 'sources' => ['x' => ['Direct', 'Role']]],
            'registry-v2',
            'roles-v1',
        ));
        $this->assertNotSame($first, $service->hash(
            ['roles' => ['B', 'A'], 'sources' => ['x' => ['Direct', 'Role']]],
            'registry-v1',
            'roles-v2',
        ));
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $first);
        $this->assertStringNotContainsString('email', $first);
        $this->assertStringNotContainsString('reason', $first);
        $this->assertStringNotContainsString('token', $first);
    }

    public function test_unchanged_confirmed_preview_applies_once(): void
    {
        $admin = $this->superAdministrator();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(UserAccessDetail::class, ['userId' => $target->getKey()])
            ->set('operation', 'assign')
            ->set('selectedRole', RoleRegistry::CMS_MANAGER)
            ->set('reason', 'Confirmed unchanged access')
            ->call('previewChange')
            ->set('confirmed', true)
            ->call('applyChange')
            ->assertSee('Access was updated');

        $this->assertTrue($target->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
        $this->assertSame(1, AuditRecord::query()->where('action', 'identity.role.assigned')->count());
    }

    public function test_direct_permission_change_invalidates_confirmation_without_mutation_or_audit(): void
    {
        $admin = $this->superAdministrator();
        $target = User::factory()->create();
        $component = Livewire::actingAs($admin)
            ->test(UserAccessDetail::class, ['userId' => $target->getKey()])
            ->set('operation', 'assign')
            ->set('selectedRole', RoleRegistry::CMS_MANAGER)
            ->set('reason', 'Preserve this reason')
            ->call('previewChange');
        $oldFingerprint = $component->get('previewFingerprint');

        $target->givePermissionTo(PermissionRegistry::USERS_VIEW);

        $component->set('confirmed', true)
            ->call('applyChange')
            ->assertSee('Access changed after this preview was prepared')
            ->assertSet('confirmed', false)
            ->assertSet('operation', 'assign')
            ->assertSet('selectedRole', RoleRegistry::CMS_MANAGER)
            ->assertSet('reason', 'Preserve this reason')
            ->assertDispatched('access-preview-stale');

        $this->assertNotSame($oldFingerprint, $component->get('previewFingerprint'));
        $this->assertFalse($target->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
        $this->assertDatabaseCount('audit_records', 0);
    }

    public function test_role_and_bundle_changes_each_invalidate_confirmation(): void
    {
        $admin = $this->superAdministrator();
        $target = User::factory()->create();
        $component = $this->preparedAssignment($admin, $target);
        $this->grantRole($target, RoleRegistry::CMS_MANAGER);

        $component->set('confirmed', true)->call('applyChange')
            ->assertSet('confirmed', false)
            ->assertSee('Access changed after this preview was prepared');
        $this->assertDatabaseCount('audit_records', 0);

        $target = User::factory()->create();
        $component = $this->preparedAssignment($admin, $target);
        Role::findByName(RoleRegistry::CMS_MANAGER, PermissionRegistry::GUARD)
            ->revokePermissionTo(PermissionRegistry::SETTINGS_VIEW);

        $component->set('confirmed', true)->call('applyChange')
            ->assertSet('confirmed', false)
            ->assertSee('Access changed after this preview was prepared');
        $this->assertFalse($target->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
        $this->assertDatabaseCount('audit_records', 0);
    }

    private function preparedAssignment(User $admin, User $target): Testable
    {
        return Livewire::actingAs($admin)
            ->test(UserAccessDetail::class, ['userId' => $target->getKey()])
            ->set('operation', 'assign')
            ->set('selectedRole', RoleRegistry::CMS_MANAGER)
            ->set('reason', 'Integrity check')
            ->call('previewChange');
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
