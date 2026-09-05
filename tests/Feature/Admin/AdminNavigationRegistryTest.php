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

        $this->assertSame(['dashboard', 'homepage', 'settings', 'pages', 'navigation', 'announcements', 'media', 'products', 'product-categories', 'collections', 'orders', 'users', 'roles', 'audit'], array_column($items, 'key'));
        $groups = app(AdminNavigationRegistry::class)->groupedVisibleFor($this->superAdministrator());
        $this->assertSame(['Overview', 'Catalogue', 'Website', 'Administration'], array_keys($groups));
        $this->assertSame(['products', 'product-categories', 'collections'], array_column($groups['Catalogue'], 'key'));

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
        $this->assertSame(['dashboard', 'homepage', 'settings', 'pages', 'navigation', 'announcements', 'media', 'products', 'product-categories', 'collections', 'audit'], array_column($registry->visibleFor($cms), 'key'));
        $this->assertSame(['dashboard', 'homepage', 'settings', 'pages', 'navigation', 'announcements', 'media', 'products', 'product-categories', 'collections', 'users', 'roles', 'audit'], array_column($registry->visibleFor($super), 'key'));

        config(['demo.enabled' => true, 'demo.allowed_environments' => ['testing']]);
        $this->assertContains('orders', array_column($registry->visibleFor($super), 'key'));
    }

    private function superAdministrator(): User
    {
        $user = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $user->assignRole(RoleRegistry::SUPER_ADMINISTRATOR));

        return $user;
    }
}
