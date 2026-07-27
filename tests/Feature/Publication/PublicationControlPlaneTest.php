<?php

namespace Tests\Feature\Publication;

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\PermissionMetadata;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Publication\Services\PublicationProjectionDiffService;
use App\Domain\Publication\Support\PublicationResourceRegistry;
use App\Domain\Publication\Support\RolloutModeResolver;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicationControlPlaneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
    }

    public function test_registry_is_exact_fail_closed_and_foundation_resources_are_static_only(): void
    {
        $registry = app(PublicationResourceRegistry::class);
        $this->assertSame(['page', 'site_content', 'product', 'collection', 'campaign'], array_keys($registry->all()));
        $this->assertNotNull($registry->get('page')->projectionResolver);
        foreach (['product', 'collection', 'campaign'] as $key) {
            $this->assertSame(['static'], $registry->get($key)->modes);
            $this->assertNull($registry->get($key)->projectionResolver);
        }
        $this->expectException(\InvalidArgumentException::class);
        $registry->get('../../arbitrary');
    }

    public function test_global_kill_switch_and_invalid_configuration_fail_to_static(): void
    {
        config(['publication_rollout.global_enabled' => false, 'publication_rollout.resources.page' => 'enabled']);
        $this->assertSame('static', app(RolloutModeResolver::class)->resolve('page')->value);
        config(['publication_rollout.global_enabled' => true, 'publication_rollout.resources.page' => 'invalid']);
        $this->assertSame('static', app(RolloutModeResolver::class)->resolve('page')->value);
        config(['publication_rollout.resources.page' => 'shadow']);
        $this->assertSame('shadow', app(RolloutModeResolver::class)->resolve('page')->value);
    }

    public function test_projection_diff_is_deterministic_bounded_and_does_not_hide_semantic_changes(): void
    {
        $service = app(PublicationProjectionDiffService::class);
        $result = $service->compare('page', ['revision_id' => 'old', 'title' => 'About', 'url' => '/about'], ['revision_id' => 'new', 'title' => 'Changed', 'url' => '/wrong'], true, CarbonImmutable::parse('2026-07-26T00:00:00Z'));
        $this->assertSame(2, $result->differenceCount);
        $this->assertEqualsCanonicalizing(['content', 'link'], $result->categories);
        $this->assertSame(64, strlen($result->evidenceReference));
    }

    public function test_emergency_permission_is_sensitive_super_only_and_has_no_control_characters(): void
    {
        $permission = PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH;
        $this->assertTrue(PermissionMetadata::isSensitive($permission));
        $this->assertContains($permission, RoleRegistry::permissionBundles()[RoleRegistry::SUPER_ADMINISTRATOR]);
        $this->assertNotContains($permission, RoleRegistry::permissionBundles()[RoleRegistry::CMS_MANAGER]);
        foreach (PermissionRegistry::all() as $slug) {
            $this->assertDoesNotMatchRegularExpression('/[\x00-\x1F\x7F]/', $slug);
        }
    }

    public function test_footer_admin_link_targets_the_protected_route_and_direct_access_is_identical(): void
    {
        $this->get('/')->assertOk()->assertSee('href="'.route('admin.dashboard').'"', false);
        $this->get('/admin')->assertRedirect(route('login'));
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get('/admin')->assertRedirect(route('verification.notice'));
        $verified = User::factory()->create();
        $this->actingAs($verified)->get('/admin?bypass=1')->assertForbidden();
        $verified->givePermissionTo(PermissionRegistry::ADMIN_ACCESS);
        $this->actingAs($verified->fresh())->get('/admin')->assertOk();
        $this->post('/admin')->assertMethodNotAllowed();
    }
}
