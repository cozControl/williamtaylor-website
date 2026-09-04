<?php

namespace Tests\Feature\SiteContent;

use App\Domain\Identity\Actions\ProvisionRegisteredAccess;
use App\Domain\Identity\Support\ControlledRoleMutation;
use App\Domain\Identity\Support\RoleRegistry;
use App\Domain\Publishing\Enums\CandidateState;
use App\Domain\SiteContent\Actions\ArchiveAnnouncement;
use App\Domain\SiteContent\Actions\EnsureSiteContent;
use App\Domain\SiteContent\Actions\RestoreAnnouncement;
use App\Domain\SiteContent\Actions\SaveSiteContentDraft;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Models\SiteContentPublicationTransition;
use App\Domain\SiteContent\Services\SiteContentWorkflow;
use App\Domain\SiteContent\Support\SiteContentFingerprint;
use App\Jobs\PublishScheduledSiteContent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class SiteContentPublishingTest extends TestCase
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

    public function test_cms_self_approval_is_allowed_for_every_current_type(): void
    {
        foreach (['primary_navigation', 'footer_navigation', 'announcement', 'site_profile'] as $type) {
            $resource = $this->resource($type, ucfirst($type));
            $this->submit($resource);
            $state = app(SiteContentWorkflow::class)->approve($this->cms, $resource, 'Policy-approved self approval', $this->fingerprint($resource));
            $this->assertSame(CandidateState::Approved, $state->candidate_state);
        }
    }

    public function test_page_permissions_cannot_mutate_navigation(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['pages.edit', 'pages.review', 'pages.approve', 'pages.publish', 'pages.schedule', 'pages.unpublish']);
        $navigation = $this->resource('primary_navigation', 'Primary');
        $this->expectException(AuthorizationException::class);
        app(SiteContentWorkflow::class)->submit($user, $navigation, 'Cross-domain attempt', $this->fingerprint($navigation));
    }

    public function test_resources_have_independent_candidate_and_published_pointers(): void
    {
        $primary = $this->resource('primary_navigation', 'Primary');
        $footer = $this->resource('footer_navigation', 'Footer');
        $this->approve($primary);
        app(SiteContentWorkflow::class)->publish($this->cms, $primary, $this->fingerprint($primary));
        $this->assertNotNull($primary->publicationState()->sole()->current_public_revision_id);
        $this->assertNull($footer->publicationState()->first()?->current_public_revision_id);
    }

    public function test_partial_overlap_blocks_and_adjacent_schedule_succeeds(): void
    {
        Carbon::setTestNow('2026-07-25 10:00:00 UTC');
        $first = $this->announcement('First', '2026-07-25 12:00:00 UTC');
        $this->approve($first);
        app(SiteContentWorkflow::class)->schedule($this->cms, $first, Carbon::parse('2026-07-25 11:00:00 UTC'), $this->fingerprint($first));
        $second = $this->announcement('Second', '2026-07-25 13:00:00 UTC');
        $this->approve($second);
        try {
            app(SiteContentWorkflow::class)->schedule($this->cms, $second, Carbon::parse('2026-07-25 11:30:00 UTC'), $this->fingerprint($second));
            $this->fail('Partial overlap was not blocked.');
        } catch (ValidationException) {
            $this->assertSame(CandidateState::Approved, $second->publicationState()->sole()->candidate_state);
        }
        app(SiteContentWorkflow::class)->schedule($this->cms, $second, Carbon::parse('2026-07-25 12:00:00 UTC'), $this->fingerprint($second));
        $this->assertSame(CandidateState::Scheduled, $second->publicationState()->sole()->candidate_state);
    }

    public function test_cancelled_and_archived_announcement_does_not_conflict_and_restore_is_audited(): void
    {
        Carbon::setTestNow('2026-07-25 10:00:00 UTC');
        $first = $this->announcement('First', '2026-07-25 14:00:00 UTC');
        $this->approve($first);
        app(SiteContentWorkflow::class)->schedule($this->cms, $first, now()->addHour(), $this->fingerprint($first));
        app(SiteContentWorkflow::class)->cancelSchedule($this->cms, $first, 'Cancelled', $this->fingerprint($first));
        app(ArchiveAnnouncement::class)->handle($this->cms, $first, 'Archived');
        $second = $this->announcement('Second', '2026-07-25 13:00:00 UTC');
        $this->approve($second);
        app(SiteContentWorkflow::class)->schedule($this->cms, $second, now()->addHour(), $this->fingerprint($second));
        app(RestoreAnnouncement::class)->handle($this->cms, $first, 'Restored');
        $this->assertDatabaseHas('audit_records', ['action' => 'site-content.restored']);
    }

    public function test_scheduled_job_is_exactly_once_and_unpublish_is_reasoned(): void
    {
        Carbon::setTestNow('2026-07-25 10:00:00 UTC');
        $profile = $this->resource('site_profile', 'Settings');
        $this->approve($profile);
        app(SiteContentWorkflow::class)->schedule($this->cms, $profile, now()->addMinute(), $this->fingerprint($profile));
        Carbon::setTestNow(now()->addMinutes(2));
        $job = new PublishScheduledSiteContent($profile->id, 'be4g1-once');
        $job->handle(app(SiteContentWorkflow::class));
        $job->handle(app(SiteContentWorkflow::class));
        $this->assertSame(1, SiteContentPublicationTransition::query()->where('job_identity', 'be4g1-once')->count());
        app(SiteContentWorkflow::class)->unpublish($this->cms, $profile, 'Operational withdrawal', $this->fingerprint($profile));
        $this->assertNull($profile->publicationState()->sole()->current_public_revision_id);
    }

    public function test_preview_is_signed_type_authorized_private_and_resource_bound(): void
    {
        $primary = $this->resource('primary_navigation', 'Primary');
        $footer = $this->resource('footer_navigation', 'Footer');
        $unsigned = route('preview.site-content.show', [$primary, $primary->currentDraftRevision]);
        $this->actingAs($this->cms)->get($unsigned)->assertForbidden();
        $signed = URL::temporarySignedRoute('preview.site-content.show', now()->addMinute(), [$primary, $primary->currentDraftRevision]);
        $response = $this->actingAs($this->cms)->get($signed)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Private storefront preview')->assertSee('<iframe', false)->assertDontSee('admin-sidebar', false);
        preg_match('/src="([^"]+)"/', $response->getContent(), $matches);
        $this->actingAs($this->cms)->get(html_entity_decode($matches[1]))
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Shop')->assertSee('2026 Collection')->assertDontSee('admin-sidebar', false);
        $mismatch = URL::temporarySignedRoute('preview.site-content.show', now()->addMinute(), [$primary, $footer->currentDraftRevision]);
        $this->actingAs($this->cms)->get($mismatch)->assertNotFound();
    }

    private function resource(string $type, string $title): SiteContent
    {
        return app(EnsureSiteContent::class)->handle($this->cms, $type, null, $title);
    }

    private function submit(SiteContent $resource): void
    {
        app(SiteContentWorkflow::class)->submit($this->cms, $resource, 'Ready for review', $this->fingerprint($resource));
    }

    private function approve(SiteContent $resource): void
    {
        $this->submit($resource);
        app(SiteContentWorkflow::class)->approve($this->cms, $resource, 'Approved', $this->fingerprint($resource));
    }

    private function fingerprint(SiteContent $resource): string
    {
        return app(SiteContentFingerprint::class)->for($resource->fresh());
    }

    private function announcement(string $title, string $end): SiteContent
    {
        $resource = $this->resource('announcement', $title);
        $payload = $resource->currentDraftRevision->payload;
        $payload['message'] = $title;
        $payload['effective_until'] = $end;
        app(SaveSiteContentDraft::class)->handle($this->cms, $resource, $resource->current_draft_revision_id, $payload, 'Set announcement window');

        return $resource->fresh();
    }
}
