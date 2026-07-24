<?php

namespace Tests\Feature\Content;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Content\Actions\ArchivePage;
use App\Domain\Content\Actions\CreatePageDraft;
use App\Domain\Content\Actions\RestorePage;
use App\Domain\Content\Actions\SavePageDraftRevision;
use App\Domain\Content\Exceptions\StaleDraftException;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class ContentFoundationTest extends TestCase
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

    public function test_exact_page_permissions_and_cms_bundle_are_registered(): void
    {
        $pagePermissions = array_values(array_filter(PermissionRegistry::all(), fn (string $permission): bool => str_starts_with($permission, 'pages.')));
        $this->assertSame(['pages.view', 'pages.create', 'pages.edit', 'pages.preview', 'pages.archive', 'pages.restore'], $pagePermissions);
        $this->assertSame([], array_values(array_filter(PermissionRegistry::all(), fn (string $permission): bool => in_array($permission, ['pages.review', 'pages.approve', 'pages.publish', 'pages.schedule', 'pages.delete'], true))));
        foreach ($pagePermissions as $permission) {
            $this->assertTrue($this->cms->can($permission));
        }
        $this->assertFalse(User::factory()->create()->can(PermissionRegistry::PAGES_VIEW));
    }

    public function test_create_generates_ulid_page_initial_revision_pointer_and_audit_atomically(): void
    {
        $page = $this->createPage();
        $this->assertTrue(Str::isUlid($page->id));
        $this->assertSame(1, $page->revisions()->count());
        $this->assertSame($page->revisions()->sole()->id, $page->current_draft_revision_id);
        $this->assertDatabaseHas('audit_records', ['action' => 'content.page.created', 'resource_identifier' => $page->id]);
    }

    public function test_slug_is_normalized_by_rule_unique_per_locale_and_reserved_values_fail(): void
    {
        $this->createPage();
        foreach (['About Us', 'about--us', '-about', 'about-', 'https://example.com', 'about?x=1', 'admin'] as $slug) {
            try {
                $this->createPage($slug);
                $this->fail("Invalid slug {$slug} unexpectedly succeeded.");
            } catch (\Throwable) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(\Throwable::class);
        $this->createPage('about-us');
    }

    public function test_save_creates_immutable_monotonic_revision_and_identical_save_is_noop(): void
    {
        $page = $this->createPage();
        $first = $page->currentDraftRevision;
        $sections = $first->payload['sections'];
        $sections[0]['data']['document']['content'][0]['content'] = [['type' => 'text', 'text' => 'A durable draft']];
        $second = app(SavePageDraftRevision::class)->handle($this->cms, $page, $first->id, 'About William Taylor', 'about-us', 'standard_page', $sections, 'Add introduction');
        $this->assertSame(2, $second->revision_number);
        $this->assertSame(2, $page->revisions()->count());
        $same = app(SavePageDraftRevision::class)->handle($this->cms, $page->fresh(), $second->id, 'About William Taylor', 'about-us', 'standard_page', $sections, null);
        $this->assertSame($second->id, $same->id);
        $this->assertSame(2, $page->revisions()->count());
        $this->expectException(LogicException::class);
        $first->update(['checksum' => str_repeat('0', 64)]);
    }

    public function test_checksum_is_deterministic_and_stale_editor_cannot_overwrite_newer_draft(): void
    {
        $page = $this->createPage();
        $first = $page->currentDraftRevision;
        $sections = $first->payload['sections'];
        $sections[0]['data']['document']['content'][0]['content'] = [['type' => 'text', 'text' => 'First editor']];
        app(SavePageDraftRevision::class)->handle($this->cms, $page, $first->id, $page->title, $page->slug, $page->template_key, $sections, 'First');
        $this->expectException(StaleDraftException::class);
        app(SavePageDraftRevision::class)->handle($this->cms, $page, $first->id, $page->title, $page->slug, $page->template_key, $sections, 'Stale');
    }

    public function test_audit_failure_rolls_back_revision_pointer_and_metadata(): void
    {
        $page = $this->createPage();
        $first = $page->currentDraftRevision;
        AuditRecord::creating(function (AuditRecord $record): void {
            if ($record->action === 'content.page.draft-saved') {
                throw new RuntimeException('Audit unavailable');
            }
        });
        try {
            app(SavePageDraftRevision::class)->handle($this->cms, $page, $first->id, 'Changed title', $page->slug, $page->template_key, $first->payload['sections'], 'Must roll back');
            $this->fail('Save unexpectedly committed.');
        } catch (RuntimeException) {
            $this->assertSame($first->id, $page->fresh()->current_draft_revision_id);
            $this->assertSame('About William Taylor', $page->fresh()->title);
            $this->assertSame(1, $page->revisions()->count());
        }
    }

    public function test_media_usage_attaches_to_new_revision_and_old_usage_remains(): void
    {
        $asset = $this->media();
        $page = $this->createPage();
        $first = $page->currentDraftRevision;
        $hero = ['key' => (string) Str::ulid(), 'type' => 'hero', 'schema_version' => 1, 'data' => ['heading' => 'Crafted in Tanzania', 'desktop_media' => ['asset_id' => $asset->id, 'alt_override' => 'Tailored jacket', 'decorative' => false]]];
        $second = app(SavePageDraftRevision::class)->handle($this->cms, $page, $first->id, $page->title, $page->slug, $page->template_key, [$hero], 'Add hero');
        $usage = MediaUsage::query()->sole();
        $this->assertSame($second->id, $usage->owner_identifier);
        $this->assertSame('Tailored jacket', $usage->alt_text_override);
        $thirdHero = $hero;
        $thirdHero['data']['desktop_media']['decorative'] = true;
        $thirdHero['data']['desktop_media']['alt_override'] = null;
        $third = app(SavePageDraftRevision::class)->handle($this->cms, $page->fresh(), $second->id, $page->title, $page->slug, $page->template_key, [$thirdHero], 'Decorative context');
        $this->assertSame(2, MediaUsage::query()->count());
        $this->assertTrue((bool) MediaUsage::query()->where('owner_identifier', $third->id)->sole()->decorative_override);
    }

    public function test_invalid_or_inaccessible_media_rolls_back_revision_and_usages(): void
    {
        $asset = $this->media(MediaAssetState::Archived, null);
        $page = $this->createPage();
        $section = ['key' => (string) Str::ulid(), 'type' => 'hero', 'schema_version' => 1, 'data' => ['heading' => 'Hero', 'desktop_media' => ['asset_id' => $asset->id, 'decorative' => false]]];
        try {
            app(SavePageDraftRevision::class)->handle($this->cms, $page, $page->current_draft_revision_id, $page->title, $page->slug, $page->template_key, [$section], 'Invalid media');
            $this->fail('Invalid media unexpectedly saved.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, $page->revisions()->count());
            $this->assertDatabaseCount('media_usages', 0);
        }
    }

    public function test_archive_restore_require_reasons_are_audited_and_archived_page_cannot_save(): void
    {
        $page = $this->createPage();
        app(ArchivePage::class)->handle($this->cms, $page, 'Campaign paused');
        $this->assertNotNull($page->fresh()->archived_at);
        $this->expectException(InvalidArgumentException::class);
        app(SavePageDraftRevision::class)->handle($this->cms, $page->fresh(), $page->current_draft_revision_id, $page->title, $page->slug, $page->template_key, $page->currentDraftRevision->payload['sections'], 'Attempt');
    }

    public function test_restore_returns_archived_page_to_active_draft_without_publishing(): void
    {
        $page = $this->createPage();
        app(ArchivePage::class)->handle($this->cms, $page, 'Archive');
        app(RestorePage::class)->handle($this->cms, $page->fresh(), 'Resume editing');
        $this->assertNull($page->fresh()->archived_at);
        $this->assertDatabaseHas('audit_records', ['action' => 'content.page.archived']);
        $this->assertDatabaseHas('audit_records', ['action' => 'content.page.restored']);
        $this->assertFalse(\Schema::hasColumn('pages', 'published_at'));
    }

    private function createPage(string $slug = 'about-us'): Page
    {
        return app(CreatePageDraft::class)->handle($this->cms, 'standard', 'About William Taylor', $slug, 'en', 'standard_page');
    }

    private function media(MediaAssetState $state = MediaAssetState::Ready, ?string $alt = 'Default meaningful alt'): MediaAsset
    {
        return MediaAsset::query()->create([
            'provider_asset_id' => 'content-'.Str::ulid(),
            'provider_public_id' => 'testing/content/'.Str::ulid(),
            'resource_type' => MediaResourceType::Image,
            'format' => 'jpg',
            'mime_type' => 'image/jpeg',
            'original_filename' => 'content.jpg',
            'internal_title' => 'Content media',
            'default_alt_text' => $alt,
            'width' => 1200,
            'height' => 1500,
            'bytes' => 1000,
            'accessibility_classification' => AccessibilityClassification::Informative,
            'state' => $state,
            'provider_metadata' => [],
            'uploaded_by' => $this->cms->id,
            'confirmed_at' => now(),
            'archived_at' => $state === MediaAssetState::Archived ? now() : null,
        ]);
    }
}
