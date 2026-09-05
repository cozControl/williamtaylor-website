<?php

namespace Tests\Feature\SiteContent;

use App\Domain\Identity\Actions\AlignFoundationRegistry;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Actions\SaveSiteContentDraft;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Support\EvidenceDatabaseGuard;
use App\Domain\SiteContent\Support\SiteContentTypeDefinition;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Livewire\Admin\SiteContent\Workspace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class SiteContentArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private User $cms;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->cms = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $this->cms->assignRole(RoleRegistry::CMS_MANAGER));
    }

    public function test_exact_permissions_replace_manage_and_page_permissions_are_not_reused(): void
    {
        $expected = [
            'navigation.view', 'navigation.edit', 'navigation.preview', 'navigation.review', 'navigation.approve', 'navigation.publish', 'navigation.schedule', 'navigation.unpublish',
            'announcements.view', 'announcements.create', 'announcements.edit', 'announcements.preview', 'announcements.review', 'announcements.approve', 'announcements.publish', 'announcements.schedule', 'announcements.unpublish', 'announcements.archive', 'announcements.restore',
            'settings.preview', 'settings.review', 'settings.approve', 'settings.publish', 'settings.schedule', 'settings.unpublish',
        ];
        foreach ($expected as $permission) {
            $this->assertTrue(PermissionRegistry::contains($permission));
            $this->assertTrue($this->cms->can($permission));
        }
        $this->assertFalse(PermissionRegistry::contains('navigation.manage'));
        $this->assertFalse(PermissionRegistry::contains('site-content.publish'));
    }

    public function test_alignment_retires_manage_and_preserves_role_assignment(): void
    {
        Permission::findOrCreate('navigation.manage')->assignRole(RoleRegistry::CMS_MANAGER);
        $this->assertContains('navigation.manage', app(AlignFoundationRegistry::class)->inspect()->removals);
        app(AlignFoundationRegistry::class)->apply('BE-4G.1 correction');
        $this->assertTrue($this->cms->fresh()->hasRole(RoleRegistry::CMS_MANAGER));
        $this->assertDatabaseMissing('permissions', ['name' => 'navigation.manage']);
        $this->assertDatabaseHas('audit_records', ['action' => 'site-content.permission-registry.aligned']);
    }

    public function test_registry_is_closed_and_defines_singletons_multi_record_and_policy(): void
    {
        $registry = app(SiteContentTypeRegistry::class);
        $this->assertSame(['primary_navigation', 'footer_navigation', 'announcement', 'site_profile'], array_keys($registry->all()));
        $this->assertTrue($registry->get('primary_navigation')->singleton);
        $this->assertTrue($registry->get('site_profile')->singleton);
        $this->assertFalse($registry->get('announcement')->singleton);
        $this->assertTrue($registry->get('announcement')->selfApprovalAllowed);
        $fixture = new SiteContentTypeDefinition('fixture', 'Fixture', 'Test only', true, ['approve' => 'settings.approve'], false, 1, [], fn () => [], fn (array $payload) => $payload, fn () => []);
        $this->assertFalse($fixture->selfApprovalAllowed);
        $this->expectException(InvalidArgumentException::class);
        $registry->get('invented');
    }

    public function test_singletons_and_multi_record_announcements_are_independent(): void
    {
        $primaryA = $this->resource('primary_navigation');
        $primaryB = $this->resource('primary_navigation');
        $profileA = $this->resource('site_profile');
        $profileB = $this->resource('site_profile');
        $announcementA = $this->resource('announcement', null, 'First');
        $announcementB = $this->resource('announcement', null, 'Second');
        $this->assertSame($primaryA->id, $primaryB->id);
        $this->assertSame($profileA->id, $profileB->id);
        $this->assertNotSame($announcementA->id, $announcementB->id);
        $this->assertNotSame($announcementA->current_draft_revision_id, $announcementB->current_draft_revision_id);
    }

    public function test_navigation_schema_accepts_two_levels_and_rejects_unsafe_links_and_duplicate_keys(): void
    {
        $primary = $this->resource('primary_navigation');
        $payload = ['items' => [['key' => 'shop', 'label' => 'Shop', 'link' => ['type' => 'internal_path', 'value' => '/shop'], 'new_tab' => false, 'visibility' => 'all', 'children' => [['key' => 'new', 'label' => 'New', 'link' => ['type' => 'external_url', 'value' => 'https://example.test/new'], 'new_tab' => true, 'visibility' => 'all']]]]];
        $revision = $this->save($primary, $payload);
        $this->assertSame('new', $revision->payload['items'][0]['children'][0]['key']);
        $payload['items'][0]['link']['value'] = 'javascript:alert(1)';
        $this->expectException(ValidationException::class);
        $this->save($primary->fresh(), $payload);
    }

    public function test_footer_groups_are_bounded_and_site_profile_strips_unknown_financial_fields(): void
    {
        $footer = $this->resource('footer_navigation');
        $this->assertSame(['company', 'customer_care', 'legal'], array_column($footer->currentDraftRevision->payload['groups'], 'key'));
        $profile = $this->resource('site_profile');
        $payload = $profile->currentDraftRevision->payload;
        $payload['contact']['whatsapp'] = '+255700000000';
        $payload['social_links'][] = ['platform' => 'instagram', 'url' => 'https://instagram.com/william', 'label' => 'Instagram'];
        $payload['currency'] = 'USD';
        $revision = $this->save($profile, $payload);
        $this->assertArrayNotHasKey('currency', $revision->payload);
    }

    public function test_unsafe_social_url_and_unapproved_platform_fail(): void
    {
        $profile = $this->resource('site_profile');
        $payload = $profile->currentDraftRevision->payload;
        $payload['social_links'][] = ['platform' => 'unknown', 'url' => 'http://example.test', 'label' => 'Unsafe'];
        $this->expectException(ValidationException::class);
        $this->save($profile, $payload);
    }

    public function test_workspace_routes_are_bounded_and_public_projection_stays_inactive(): void
    {
        $this->resource('primary_navigation');
        $this->resource('footer_navigation');
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->actingAs($this->cms)->get(route('admin.content.navigation.index'))->assertOk();
        $this->assertLessThanOrEqual(30, $queries);
        $this->actingAs($this->cms)->get(route('admin.content.announcements.index'))->assertOk();
        $this->actingAs($this->cms)->get(route('admin.settings.index'))->assertOk();
        $this->get('/')->assertOk()->assertDontSee('Public storefront projection is not active yet');
    }

    public function test_site_settings_save_is_direct_audited_and_effective(): void
    {
        $profile = $this->resource('site_profile');
        $payload = $profile->currentDraftRevision->payload;
        $payload['brand']['name'] = 'William Taylor Direct';

        Livewire::actingAs($this->cms)
            ->test(Workspace::class, ['siteContent' => $profile])
            ->set('content', $payload)
            ->call('save')
            ->assertSee('Site settings saved.');

        $profile->refresh()->load('publicationState');
        $this->assertSame($profile->current_draft_revision_id, $profile->publicationState->current_public_revision_id);
        $this->assertDatabaseHas('audit_records', ['action' => 'site-content.saved-live']);
    }

    public function test_evidence_database_guard_refuses_willy_memory_and_empty_paths(): void
    {
        foreach ([base_path('willy'), ':memory:', ''] as $path) {
            try {
                EvidenceDatabaseGuard::assertDisposable($path);
                $this->fail("Guard accepted [{$path}].");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
        EvidenceDatabaseGuard::assertDisposable(storage_path('app/evidence/disposable.sqlite'));
        $this->addToAssertionCount(1);
    }

    private function resource(string $type, ?string $key = null, ?string $title = null): SiteContent
    {
        return app(EnsureSiteContent::class)->handle($this->cms, $type, $key, $title);
    }

    /** @param array<string, mixed> $payload */
    private function save(SiteContent $resource, array $payload)
    {
        return app(SaveSiteContentDraft::class)->handle($this->cms, $resource, $resource->current_draft_revision_id, $payload, 'Typed test revision');
    }
}
