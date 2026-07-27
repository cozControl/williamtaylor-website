<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
    }

    public function test_guest_unverified_and_ordinary_users_cannot_cross_the_admin_boundary(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $unverified = User::factory()->unverified()->create();
        $this->grantRole($unverified, RoleRegistry::CMS_MANAGER);
        $this->actingAs($unverified)->get(route('admin.dashboard'))->assertRedirect(route('verification.notice'));

        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->get(route('admin.dashboard'))->assertForbidden();

        foreach (['admin.access.users.index', 'admin.access.roles.index', 'admin.audit.index', 'admin.settings.index'] as $route) {
            $this->actingAs($ordinary)->get(route($route))->assertForbidden();
        }
    }

    public function test_cms_manager_sees_only_its_permitted_destinations(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, RoleRegistry::CMS_MANAGER);

        $response = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $response->assertSee('Audit log')->assertSee('Site settings');
        $response->assertDontSee('href="'.route('admin.access.users.index').'"', false);
        $response->assertDontSee('href="'.route('admin.access.roles.index').'"', false);

        $this->get(route('admin.audit.index'))->assertOk();
        $this->get(route('admin.settings.index'))->assertOk();
        $this->get(route('admin.access.users.index'))->assertForbidden();
        $this->get(route('admin.access.roles.index'))->assertForbidden();
    }

    public function test_super_administrator_can_open_every_registered_destination(): void
    {
        $user = User::factory()->create();
        $this->grantRole($user, RoleRegistry::SUPER_ADMINISTRATOR);

        foreach (['admin.dashboard', 'admin.access.users.index', 'admin.access.roles.index', 'admin.audit.index', 'admin.settings.index'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk();
        }
    }

    public function test_audit_and_settings_remain_placeholders_without_domain_records_or_management_controls(): void
    {
        $administrator = User::factory()->create();
        $this->grantRole($administrator, RoleRegistry::SUPER_ADMINISTRATOR);

        foreach (['admin.audit.index', 'admin.settings.index'] as $route) {
            $response = $this->actingAs($administrator)->get(route($route))->assertOk();
            $response->assertDontSee('Sensitive Placeholder Sentinel');
            $response->assertDontSee('<table', false);
            $response->assertDontSee('data-admin-mutation', false);
        }
    }

    public function test_layout_has_accessibility_privacy_and_context_landmarks(): void
    {
        $user = User::factory()->create(['name' => '<script>alert("x")</script>']);
        $this->grantRole($user, RoleRegistry::CMS_MANAGER);

        $response = $this->actingAs($user)->get(route('admin.audit.index'))->assertOk();
        $response->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $response->assertSee('href="#admin-main"', false);
        $response->assertSee('id="admin-main"', false);
        $response->assertSee('aria-current="page"', false);
        $response->assertSee('aria-label="Open administration navigation"', false);
        $response->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert("x")</script>', false);
    }

    private function grantRole(User $user, string $role): void
    {
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole($role));
    }
}
