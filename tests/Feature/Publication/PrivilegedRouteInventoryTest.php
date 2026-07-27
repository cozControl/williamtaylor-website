<?php

namespace Tests\Feature\Publication;

use App\Domain\Publication\Support\PrivilegedRouteInventory;
use Tests\TestCase;

final class PrivilegedRouteInventoryTest extends TestCase
{
    public function test_every_admin_and_preview_route_has_its_required_direct_access_boundary(): void
    {
        $inventory = app(PrivilegedRouteInventory::class)->all();
        $this->assertNotEmpty($inventory);
        foreach ($inventory as $route) {
            if (str_starts_with($route['uri'], 'admin')) {
                $this->assertContains('auth', $route['middleware'], $route['uri']);
                $this->assertContains('verified', $route['middleware'], $route['uri']);
                $this->assertContains('can:admin.access', $route['middleware'], $route['uri']);
            }
            if (str_starts_with($route['uri'], 'preview/')) {
                $this->assertContains('signed', $route['middleware'], $route['uri']);
            }
        }
        $names = array_filter(array_column($inventory, 'name'));
        $this->assertSame(count($names), count(array_unique($names)));
        $this->assertFalse(collect($inventory)->contains(fn (array $route) => $route['uri'] === 'html/admin.html'));
    }
}
