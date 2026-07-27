<?php

namespace Tests\Feature\Content;

use App\Domain\Content\Actions\CreatePageDraft;
use App\Domain\Content\Actions\CreatePagePreviewUrl;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Livewire\Admin\Content\Pages\PageIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ContentAdministrationAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $cms;

    private Page $page;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->cms = User::factory()->create();
        app(ControlledRoleMutation::class)->run(fn () => $this->cms->assignRole(RoleRegistry::CMS_MANAGER));
        $this->page = app(CreatePageDraft::class)->handle($this->cms, 'standard', 'Editorial story', 'editorial-story', 'en', 'standard_page');
    }

    public function test_exact_admin_routes_are_permission_protected_and_no_public_cms_route_exists(): void
    {
        $ordinary = User::factory()->create();
        foreach ([
            route('admin.content.pages.index'),
            route('admin.content.pages.create'),
            route('admin.content.pages.show', $this->page),
            route('admin.content.pages.edit', $this->page),
        ] as $url) {
            $this->actingAs($ordinary)->get($url)->assertForbidden();
            $this->actingAs($this->cms)->get($url)->assertOk();
        }
        $this->get('/editorial-story')->assertNotFound();
    }

    public function test_index_search_filter_and_allowlisted_sort_work_without_raw_payload(): void
    {
        Livewire::actingAs($this->cms)->test(PageIndex::class)
            ->assertSee('Editorial story')
            ->set('search', 'missing')
            ->assertSee('No matching draft pages')
            ->set('search', '')
            ->set('type', 'standard')
            ->set('sort', 'unsafe_column')
            ->assertSee('Editorial story')
            ->assertDontSee('"schema_version"', false);
    }

    public function test_preview_requires_auth_verification_permission_signature_and_ownership(): void
    {
        $url = app(CreatePagePreviewUrl::class)->handle($this->cms, $this->page, $this->page->currentDraftRevision);
        $this->get($url)->assertRedirect(route('login'));
        $unverified = User::factory()->unverified()->create();
        $this->actingAs($unverified)->get($url)->assertRedirect(route('verification.notice'));
        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->get($url)->assertForbidden();
        $this->actingAs($this->cms)->get(route('preview.pages.show', [$this->page, $this->page->currentDraftRevision]))->assertForbidden();
        $other = app(CreatePageDraft::class)->handle($this->cms, 'standard', 'Other', 'other', 'en', 'standard_page');
        $bad = \URL::temporarySignedRoute('preview.pages.show', now()->addMinutes(15), ['page' => $other, 'revision' => $this->page->currentDraftRevision]);
        $this->actingAs($this->cms)->get($bad)->assertNotFound();
    }

    public function test_preview_is_private_noindex_exact_revision_and_has_no_edit_controls(): void
    {
        $revision = $this->page->currentDraftRevision;
        $url = app(CreatePagePreviewUrl::class)->handle($this->cms, $this->page, $revision);
        $this->actingAs($this->cms)->get($url)
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Draft preview')
            ->assertSee('Revision 1')
            ->assertDontSee('Save draft')
            ->assertDontSee('Publish')
            ->assertDontSee('Schedule');
    }

    public function test_page_editor_links_to_workflow_without_embedding_high_impact_actions(): void
    {
        $this->actingAs($this->cms)->get(route('admin.content.pages.edit', $this->page))
            ->assertOk()
            ->assertSee('Save draft')
            ->assertSee('Preview saved revision')
            ->assertSee('Open review and publishing workflow')
            ->assertDontSee('Designate published now')
            ->assertDontSee('Approve candidate')
            ->assertDontSee('Schedule approved revision');
    }

    public function test_expired_preview_signature_is_rejected_and_archived_editor_is_read_only(): void
    {
        $expired = \URL::temporarySignedRoute(
            'preview.pages.show',
            now()->subMinute(),
            ['page' => $this->page, 'revision' => $this->page->currentDraftRevision],
        );
        $this->actingAs($this->cms)->get($expired)->assertForbidden();

        $this->page->forceFill(['archived_at' => now()])->save();
        $this->actingAs($this->cms)
            ->get(route('admin.content.pages.edit', $this->page))
            ->assertStatus(409)
            ->assertSee('Archived pages are read-only');
    }

    public function test_page_index_and_preview_remain_within_query_budgets(): void
    {
        $indexQueries = 0;
        \DB::listen(function () use (&$indexQueries): void {
            $indexQueries++;
        });
        $this->actingAs($this->cms)->get(route('admin.content.pages.index'))->assertOk();
        $this->assertLessThanOrEqual(20, $indexQueries);

        $previewQueries = 0;
        \DB::listen(function () use (&$previewQueries): void {
            $previewQueries++;
        });
        $url = app(CreatePagePreviewUrl::class)->handle($this->cms, $this->page, $this->page->currentDraftRevision);
        $this->actingAs($this->cms)->get($url)->assertOk();
        $this->assertLessThanOrEqual(20, $previewQueries);
    }

    public function test_read_only_page_user_sees_index_but_not_create_edit_or_preview(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo([PermissionRegistry::ADMIN_ACCESS, PermissionRegistry::PAGES_VIEW]);
        $this->actingAs($viewer)->get(route('admin.content.pages.index'))->assertOk();
        $this->actingAs($viewer)->get(route('admin.content.pages.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('admin.content.pages.edit', $this->page))->assertForbidden();
    }
}
