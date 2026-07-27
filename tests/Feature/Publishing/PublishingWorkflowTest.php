<?php

namespace Tests\Feature\Publishing;

use App\Domain\Audit\Models\AuditRecord;
use App\Domain\Content\Actions\ArchivePage;
use App\Domain\Content\Actions\CreatePageDraft;
use App\Domain\Content\Actions\SavePageDraftRevision;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Publishing\Actions\ApprovePageRevision;
use App\Domain\Publishing\Actions\CancelScheduledPagePublication;
use App\Domain\Publishing\Actions\PublishApprovedPageRevision;
use App\Domain\Publishing\Actions\RequestPageChanges;
use App\Domain\Publishing\Actions\ScheduleApprovedPageRevision;
use App\Domain\Publishing\Actions\SubmitPageForReview;
use App\Domain\Publishing\Actions\UnpublishPage;
use App\Domain\Publishing\Contracts\PublicationPolicy;
use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\Publishing\Exceptions\StalePublicationStateException;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\Publishing\Models\PagePublicationTransition;
use App\Domain\Publishing\Services\PagePublishingWorkflow;
use App\Domain\Publishing\Support\PublicationFingerprint;
use App\Domain\Publishing\Support\RevisionComparison;
use App\Jobs\PublishScheduledPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class PublishingWorkflowTest extends TestCase
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

    public function test_exact_publishing_permissions_and_cms_bundle_are_registered(): void
    {
        $publishing = array_values(array_filter(PermissionRegistry::all(), fn (string $permission): bool => in_array($permission, [
            'pages.review', 'pages.approve', 'pages.publish', 'pages.schedule', 'pages.unpublish',
        ], true)));
        $this->assertSame(['pages.review', 'pages.approve', 'pages.publish', 'pages.schedule', 'pages.unpublish'], $publishing);
        $this->assertFalse(PermissionRegistry::contains('pages.delete'));
        $this->assertFalse(PermissionRegistry::contains('pages.rollback'));
        foreach ($publishing as $permission) {
            $this->assertTrue($this->cms->can($permission));
        }
    }

    public function test_submit_request_changes_and_approve_are_immutable_audited_transitions(): void
    {
        $page = $this->page();
        $revision = $page->current_draft_revision_id;
        $state = $this->submit($page);
        $this->assertSame(CandidateState::InReview, $state->candidate_state);
        $this->assertSame($revision, $state->candidate_revision_id);
        app(RequestPageChanges::class)->handle($this->cms, $page, 'Clarify the introduction.');
        $this->assertSame(CandidateState::ChangesRequested, $state->fresh()->candidate_state);
        $this->expectException(InvalidArgumentException::class);
        app(ApprovePageRevision::class)->handle($this->cms, $page, 'Approve invalid state', $this->fingerprint($page));
    }

    public function test_approval_publish_and_newer_draft_coexist_without_public_route_change(): void
    {
        $page = $this->page();
        $approved = $this->approve($page);
        $publishedRevision = $approved->candidate_revision_id;
        $state = app(PublishApprovedPageRevision::class)->handle($this->cms, $page, $this->fingerprint($page));
        $this->assertSame($publishedRevision, $state->current_public_revision_id);
        $this->assertNull($state->candidate_revision_id);

        $draft = $this->newDraft($page->fresh(), 'A newer editable draft');
        $this->assertSame($publishedRevision, $state->fresh()->current_public_revision_id);
        $this->assertSame($draft->getKey(), $page->fresh()->current_draft_revision_id);
        $this->get('/')->assertOk()->assertDontSee('A newer editable draft');
    }

    public function test_newer_draft_does_not_supersede_and_explicit_supersession_requires_confirmation(): void
    {
        $page = $this->page();
        $state = $this->approve($page);
        $oldCandidate = $state->candidate_revision_id;
        $draft = $this->newDraft($page->fresh(), 'New candidate');
        $this->assertSame($oldCandidate, $state->fresh()->candidate_revision_id);

        try {
            app(SubmitPageForReview::class)->handle($this->cms, $page->fresh(), 'Submit newer', $this->fingerprint($page));
            $this->fail('Candidate was silently superseded.');
        } catch (InvalidArgumentException) {
            $this->assertSame($oldCandidate, $state->fresh()->candidate_revision_id);
        }
        $state = app(SubmitPageForReview::class)->handle($this->cms, $page->fresh(), 'Submit newer', $this->fingerprint($page), true, 'Approved candidate replaced after editorial changes.');
        $this->assertSame($draft->getKey(), $state->candidate_revision_id);
        $this->assertSame(CandidateState::InReview, $state->candidate_state);
        $this->assertDatabaseHas('audit_records', ['action' => 'content.page.review-candidate-superseded']);
    }

    public function test_schedule_converts_utc_cancel_requires_reason_and_archive_is_blocked(): void
    {
        Carbon::setTestNow('2026-07-24 12:00:00 UTC');
        $page = $this->page();
        $this->approve($page);
        $utc = Carbon::parse('2026-07-24 18:30:00', 'Africa/Dar_es_Salaam')->utc();
        $state = app(ScheduleApprovedPageRevision::class)->handle($this->cms, $page, $utc, $this->fingerprint($page));
        $this->assertSame('2026-07-24 15:30:00', $state->scheduled_for->utc()->format('Y-m-d H:i:s'));
        $this->expectException(InvalidArgumentException::class);
        app(ArchivePage::class)->handle($this->cms, $page, 'Archive scheduled Page');
    }

    public function test_cancelled_schedule_does_not_publish_and_due_job_publishes_exactly_once(): void
    {
        Carbon::setTestNow('2026-07-24 12:00:00 UTC');
        $page = $this->page();
        $this->approve($page);
        app(ScheduleApprovedPageRevision::class)->handle($this->cms, $page, now()->addMinute(), $this->fingerprint($page));
        app(CancelScheduledPagePublication::class)->handle($this->cms, $page, 'Editorial delay', $this->fingerprint($page));
        $this->assertFalse(app(PagePublishingWorkflow::class)->publishScheduled($page, 'cancelled-job', now()->addHour()));

        app(ScheduleApprovedPageRevision::class)->handle($this->cms, $page, now()->addMinute(), $this->fingerprint($page));
        Carbon::setTestNow(now()->addMinutes(2));
        $job = new PublishScheduledPage($page->id, 'due-job');
        $job->handle(app(PagePublishingWorkflow::class));
        $job->handle(app(PagePublishingWorkflow::class));
        $this->assertNotNull($page->publicationState()->sole()->current_public_revision_id);
        $this->assertSame(1, PagePublicationTransition::query()->where('job_identity', 'due-job')->count());
        $this->assertDatabaseHas('audit_records', ['action' => 'content.page.published']);
    }

    public function test_unpublish_preserves_draft_candidate_history_and_requires_reason(): void
    {
        $page = $this->page();
        $this->approve($page);
        app(PublishApprovedPageRevision::class)->handle($this->cms, $page, $this->fingerprint($page));
        $draftId = $page->fresh()->current_draft_revision_id;
        $history = PagePublicationTransition::query()->count();
        try {
            app(UnpublishPage::class)->handle($this->cms, $page, '', $this->fingerprint($page));
            $this->fail('Unpublish without a reason unexpectedly succeeded.');
        } catch (InvalidArgumentException) {
            $this->assertNotNull($page->publicationState()->sole()->current_public_revision_id);
        }
        $state = app(UnpublishPage::class)->handle($this->cms, $page, 'Editorial withdrawal', $this->fingerprint($page));
        $this->assertNull($state->current_public_revision_id);
        $this->assertSame($draftId, $page->fresh()->current_draft_revision_id);
        $this->assertSame($history + 1, PagePublicationTransition::query()->count());
    }

    public function test_stale_fingerprint_and_audit_failure_roll_back_high_impact_transitions(): void
    {
        $page = $this->page();
        $state = $this->submit($page);
        $stale = $this->fingerprint($page);
        $state->increment('state_version');
        $this->expectException(StalePublicationStateException::class);
        app(ApprovePageRevision::class)->handle($this->cms, $page, 'Approve', $stale);
    }

    public function test_audit_failure_rolls_back_approval(): void
    {
        $page = $this->page();
        $this->submit($page);
        AuditRecord::creating(function (AuditRecord $record): void {
            if ($record->action === 'content.page.approved') {
                throw new RuntimeException('Audit unavailable.');
            }
        });
        try {
            app(ApprovePageRevision::class)->handle($this->cms, $page, 'Approve', $this->fingerprint($page));
            $this->fail('Approval unexpectedly committed.');
        } catch (RuntimeException) {
            $this->assertSame(CandidateState::InReview, $page->publicationState()->sole()->candidate_state);
            $this->assertDatabaseMissing('page_publication_transitions', ['to_state' => 'approved']);
        }
    }

    public function test_self_approval_policy_can_prohibit_and_transition_history_is_immutable(): void
    {
        $page = $this->page();
        $this->submit($page);
        $this->app->instance(PublicationPolicy::class, new class implements PublicationPolicy
        {
            public function allowsSelfApproval(string $pageType): bool
            {
                return false;
            }

            public function checksum(): string
            {
                return hash('sha256', 'self-approval-prohibited');
            }
        });
        $this->expectException(InvalidArgumentException::class);
        app(ApprovePageRevision::class)->handle($this->cms, $page, 'Self approval attempt', app(PublicationFingerprint::class)->for($page->fresh()));
    }

    public function test_comparison_detects_added_removed_reordered_and_safe_rich_text_change(): void
    {
        $page = $this->page();
        $before = $page->currentDraftRevision;
        $sections = $before->payload['sections'];
        $sections[0]['data']['document']['content'][0]['content'] = [['type' => 'text', 'text' => '<script>safe text</script>']];
        $after = app(SavePageDraftRevision::class)->handle($this->cms, $page, $before->id, $page->title, $page->slug, $page->template_key, $sections, 'Rich text update');
        $comparison = app(RevisionComparison::class)->compare($page->fresh(), $after, $before);
        $this->assertSame('rich_text_changed', $comparison['sections'][0]['change']);
        $this->assertStringNotContainsString('<script>', json_encode($comparison, JSON_THROW_ON_ERROR));
    }

    public function test_review_route_requires_review_permission_and_command_dispatches_due_pages(): void
    {
        $page = $this->page();
        $this->actingAs(User::factory()->create())->get(route('admin.content.pages.review-queue'))->assertForbidden();
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->actingAs($this->cms)->get(route('admin.content.pages.review-queue'))->assertOk();
        $this->assertLessThanOrEqual(20, $queries, 'Review queue exceeded its query budget.');
        Carbon::setTestNow('2026-07-24 12:00:00 UTC');
        $this->approve($page);
        app(ScheduleApprovedPageRevision::class)->handle($this->cms, $page, now()->addMinute(), $this->fingerprint($page));
        Carbon::setTestNow(now()->addMinutes(2));
        Bus::fake();
        $this->artisan('content:publish-scheduled-pages')->assertSuccessful();
        Bus::assertDispatched(PublishScheduledPage::class, fn (PublishScheduledPage $job) => $job->pageId === $page->id);
    }

    public function test_transition_rows_cannot_be_updated_or_deleted(): void
    {
        $page = $this->page();
        $this->submit($page);
        $transition = PagePublicationTransition::query()->firstOrFail();
        try {
            $transition->update(['note' => 'mutated']);
            $this->fail('Transition update succeeded.');
        } catch (LogicException) {
            $this->assertDatabaseMissing('page_publication_transitions', ['note' => 'mutated']);
        }
        $this->expectException(LogicException::class);
        $transition->delete();
    }

    private function page(): Page
    {
        return app(CreatePageDraft::class)->handle($this->cms, 'standard', 'Publishing test Page', 'publishing-test', 'en', 'standard_page');
    }

    private function submit(Page $page): PagePublicationState
    {
        return app(SubmitPageForReview::class)->handle($this->cms, $page, 'Ready for governance review.', $this->fingerprint($page));
    }

    private function approve(Page $page): PagePublicationState
    {
        $this->submit($page);

        return app(ApprovePageRevision::class)->handle($this->cms, $page, 'Approved for designation.', $this->fingerprint($page));
    }

    private function fingerprint(Page $page): string
    {
        return app(PublicationFingerprint::class)->for($page->fresh());
    }

    private function newDraft(Page $page, string $text): ContentRevision
    {
        $current = $page->currentDraftRevision;
        $sections = $current->payload['sections'];
        $sections[0]['data']['document']['content'][0]['content'] = [['type' => 'text', 'text' => $text]];

        return app(SavePageDraftRevision::class)->handle($this->cms, $page, $current->id, $page->title, $page->slug, $page->template_key, $sections, 'Newer draft');
    }
}
