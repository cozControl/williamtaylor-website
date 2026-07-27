<?php

namespace Tests\Feature\Publication;

use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Publication\Actions\EmergencyUnpublish;
use App\Domain\Publication\Actions\RollbackToNewDraft;
use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Models\SiteContentPublicationState;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

final class PublicationMutationHardeningTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        app(ProvisionRegisteredAccess::class)->handle();
        $this->actor = User::factory()->create();
        $this->actor->givePermissionTo(PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH);
    }

    public function test_page_emergency_unpublish_is_scoped_after_commit_and_idempotent(): void
    {
        [$page, $revision] = $this->page();
        PagePublicationState::query()->create(['page_id' => $page->id, 'current_public_revision_id' => $revision->id, 'state_version' => 1]);
        Cache::put('public-page:cached', 'page');
        Cache::put('public-page:index:'.$page->id, ['public-page:cached']);
        Cache::put('unrelated', 'preserved');

        $first = app(EmergencyUnpublish::class)->handle($this->actor, $page, 'Immediate static fallback required.');
        $retry = app(EmergencyUnpublish::class)->handle($this->actor, $page, 'Idempotent retry.');

        $this->assertTrue($first->transitioned);
        $this->assertFalse($retry->transitioned);
        $this->assertNull($page->publicationState()->firstOrFail()->current_public_revision_id);
        $this->assertFalse(Cache::has('public-page:cached'));
        $this->assertSame('preserved', Cache::get('unrelated'));
        $this->assertDatabaseCount('audit_records', 1);
        $this->assertDatabaseHas('content_revisions', ['id' => $revision->id]);
    }

    public function test_site_content_emergency_unpublish_cancels_schedule_without_touching_other_state(): void
    {
        [$content, $revision] = $this->siteContent();
        SiteContentPublicationState::query()->create(['site_content_id' => $content->id, 'candidate_revision_id' => $revision->id, 'candidate_state' => CandidateState::Scheduled, 'current_public_revision_id' => $revision->id, 'scheduled_by' => $this->actor->id, 'scheduled_for' => now()->addHour(), 'state_version' => 1]);
        Cache::put('public-site-content:cached', 'site');
        Cache::put('public-site-content:index:'.$content->id, ['public-site-content:cached']);

        app(EmergencyUnpublish::class)->handle($this->actor, $content, 'Suspend projected chrome now.');
        $state = $content->publicationState()->firstOrFail();

        $this->assertNull($state->current_public_revision_id);
        $this->assertSame(CandidateState::Approved, $state->candidate_state);
        $this->assertNull($state->scheduled_for);
        $this->assertFalse(Cache::has('public-site-content:cached'));
    }

    public function test_emergency_denials_and_validation_failures_have_zero_side_effects(): void
    {
        [$page, $revision] = $this->page();
        PagePublicationState::query()->create(['page_id' => $page->id, 'current_public_revision_id' => $revision->id, 'state_version' => 1]);
        $unauthorized = User::factory()->create();

        try {
            app(EmergencyUnpublish::class)->handle($unauthorized, $page, 'Not permitted.');
            $this->fail('Unauthorized transition succeeded.');
        } catch (AuthorizationException) {
            $this->assertSame($revision->id, $page->publicationState()->firstOrFail()->current_public_revision_id);
        }
        $this->expectException(InvalidArgumentException::class);
        app(EmergencyUnpublish::class)->handle($this->actor, $page, ' ');
    }

    public function test_rollback_creates_new_immutable_draft_with_provenance_and_stale_retry_fails(): void
    {
        [$page, $source] = $this->page();
        $original = $source->getAttributes();
        $result = app(RollbackToNewDraft::class)->handle($this->actor, $page, $source, $source->id, 'Return historical content to review.');
        $created = ContentRevision::query()->findOrFail($result->revisionId);

        $this->assertSame(2, $result->revisionNumber);
        $this->assertSame($source->id, $created->source_revision_id);
        $this->assertSame($result->revisionId, $page->fresh()->current_draft_revision_id);
        $this->assertSame(1, $source->fresh()->revision_number);
        $this->assertSame(hash('sha256', json_encode(['page' => ['title' => 'About'], 'sections' => []], JSON_THROW_ON_ERROR)), $source->fresh()->checksum);
        $this->assertDatabaseCount('audit_records', 1);

        $this->expectException(InvalidArgumentException::class);
        app(RollbackToNewDraft::class)->handle($this->actor, $page->fresh(), $source, $source->id, 'Duplicate retry.');
    }

    public function test_rollback_rejects_cross_resource_revision_without_mutation_or_audit(): void
    {
        [$page] = $this->page();
        [, $foreign] = $this->page('foreign-page');

        try {
            app(RollbackToNewDraft::class)->handle($this->actor, $page, $foreign, $page->current_draft_revision_id, 'Forged source.');
            $this->fail('Cross-resource rollback succeeded.');
        } catch (InvalidArgumentException) {
            $this->assertSame(1, $page->revisions()->count());
            $this->assertDatabaseCount('audit_records', 0);
        }
    }

    /** @return array{Page, ContentRevision} */
    private function page(string $slug = 'publication-hardening'): array
    {
        $page = Page::query()->create(['type' => 'standard', 'locale' => 'en', 'title' => 'About', 'slug' => $slug, 'template_key' => 'standard', 'created_by' => $this->actor->id, 'updated_by' => $this->actor->id]);
        $revision = $this->revision(Page::class, $page->id);
        $page->forceFill(['current_draft_revision_id' => $revision->id])->save();

        return [$page, $revision];
    }

    /** @return array{SiteContent, ContentRevision} */
    private function siteContent(): array
    {
        $content = SiteContent::query()->create(['type' => 'site_profile', 'key' => 'global', 'locale' => 'en', 'title' => 'Site profile', 'created_by' => $this->actor->id, 'updated_by' => $this->actor->id, 'lock_version' => 1]);
        $revision = $this->revision(SiteContent::class, $content->id);
        $content->forceFill(['current_draft_revision_id' => $revision->id])->save();

        return [$content, $revision];
    }

    private function revision(string $type, string $id): ContentRevision
    {
        $payload = ['page' => ['title' => 'About'], 'sections' => []];

        return ContentRevision::query()->create(['resource_type' => $type, 'resource_id' => $id, 'revision_number' => 1, 'schema_version' => 1, 'payload' => $payload, 'checksum' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'sanitizer_version' => '1', 'change_summary' => 'Initial', 'created_by' => $this->actor->id, 'created_at' => now('UTC')]);
    }
}
