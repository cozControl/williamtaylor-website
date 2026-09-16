<?php

namespace Tests\Feature\Admin;

use App\Domain\Admin\Navigation\AdminNavigationRegistry;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminNavigationRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
    }

    public function test_registry_has_stable_order_registered_routes_and_registered_permissions(): void
    {
        $items = app(AdminNavigationRegistry::class)->all();

        $this->assertSame(['dashboard', 'homepage', 'settings', 'pages', 'navigation', 'announcements', 'media', 'catalogue', 'products', 'product-categories', 'collections', 'campaigns', 'inventory', 'orders', 'demo-orders', 'users', 'roles', 'audit'], array_column($items, 'key'));
        $groups = app(AdminNavigationRegistry::class)->groupedVisibleFor($this->superAdministrator());
        $this->assertSame(['Overview', 'Catalogue', 'Commerce', 'Website', 'Administration'], array_keys($groups));
        $this->assertSame(['catalogue', 'products', 'product-categories', 'collections', 'campaigns', 'inventory'], array_column($groups['Catalogue'], 'key'));
        $this->assertSame(['catalogue', 'products', 'categories', 'collections', 'campaigns', 'catalogue'], array_column($groups['Catalogue'], 'icon'));

        foreach ($items as $item) {
            $this->assertTrue(Route::has($item->routeName));
            $this->assertTrue(PermissionRegistry::contains($item->permission));
            $this->assertTrue($item->isActive($item->routeName));
        }
    }

    public function test_visibility_is_derived_from_permissions_not_role_names(): void
    {
        $registry = app(AdminNavigationRegistry::class);
        $ordinary = User::factory()->create();
        $cms = User::factory()->create();
        $super = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $cms->assignRole(RoleRegistry::CMS_MANAGER));
        app(ControlledRoleMutation::class)->run(fn () => $super->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));

        $this->assertSame([], $registry->visibleFor(null));
        $this->assertSame([], $registry->visibleFor($ordinary));
        $this->assertSame(['dashboard', 'homepage', 'settings', 'pages', 'navigation', 'announcements', 'media', 'catalogue', 'products', 'product-categories', 'collections', 'campaigns', 'audit'], array_column($registry->visibleFor($cms), 'key'));
        $this->assertSame(['dashboard', 'homepage', 'settings', 'pages', 'navigation', 'announcements', 'media', 'catalogue', 'products', 'product-categories', 'collections', 'campaigns', 'inventory', 'orders', 'users', 'roles', 'audit'], array_column($registry->visibleFor($super), 'key'));

        config(['demo.enabled' => true, 'demo.allowed_environments' => ['testing']]);
        $this->assertContains('orders', array_column($registry->visibleFor($super), 'key'));
        $this->assertContains('demo-orders', array_column($registry->visibleFor($super), 'key'));
    }

    public function test_media_library_navigation_and_route_remain_permission_aware(): void
    {
        $ordinary = User::factory()->create(['email_verified_at' => now()]);
        $ordinary->givePermissionTo(PermissionRegistry::ADMIN_ACCESS);
        $manager = User::factory()->create(['email_verified_at' => now()]);
        app(ControlledRoleMutation::class)->run(fn () => $manager->assignRole(RoleRegistry::CMS_MANAGER));

        $this->actingAs($ordinary)->get(route('admin.dashboard'))->assertOk()->assertDontSeeText('Media library');
        $this->actingAs($ordinary)->get(route('admin.media.index'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk()
            ->assertSeeText('Media library')->assertSee(route('admin.media.index'), false)
            ->assertSee('class="admin-sidebar-navigation"', false)
            ->assertSee('class="admin-drawer-navigation"', false)
            ->assertSee('data-admin-active-nav-link', false)
            ->assertSee('grid-template-rows:auto minmax(0,1fr) auto!important', false);

        $navigationScript = file_get_contents(resource_path('js/admin.js'));
        $this->assertIsString($navigationScript);
        $this->assertStringContainsString('revealActiveNavigationLink', $navigationScript);
        $this->assertStringContainsString("navigation.scrollTo({ top: Math.max(0, target), behavior: 'auto' })", $navigationScript);

        $navigationStyles = file_get_contents(resource_path('css/admin.css'));
        $this->assertIsString($navigationStyles);
        $this->assertStringContainsString('scrollbar-color:#8f7340 #211f1a', $navigationStyles);
        $this->assertStringContainsString('.admin-sidebar-navigation::-webkit-scrollbar-thumb', $navigationStyles);
        $this->assertStringContainsString('background:#c3a264', $navigationStyles);
    }

    private function superAdministrator(): User
    {
        $user = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));

        return $user;
    }
}
