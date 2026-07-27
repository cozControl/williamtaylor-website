<?php

namespace App\Livewire\Admin\Content\Pages;

use App\Domain\Content\Actions\ArchivePage;
use App\Domain\Content\Actions\CreatePagePreviewUrl;
use App\Domain\Content\Actions\RestorePage;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Models\Page;
use App\Domain\Publishing\Actions\ApprovePageRevision;
use App\Domain\Publishing\Actions\CancelScheduledPagePublication;
use App\Domain\Publishing\Actions\PublishApprovedPageRevision;
use App\Domain\Publishing\Actions\RequestPageChanges;
use App\Domain\Publishing\Actions\ScheduleApprovedPageRevision;
use App\Domain\Publishing\Actions\SubmitPageForReview;
use App\Domain\Publishing\Actions\UnpublishPage;
use App\Domain\Publishing\Support\PublicationFingerprint;
use App\Domain\Publishing\Support\PublicationReadiness;
use App\Domain\Publishing\Support\RevisionComparison;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class PageDetail extends Component
{
    public string $pageId;

    public string $reason = '';

    public string $workflowNote = '';

    public string $scheduleLocal = '';

    public bool $confirmSupersession = false;

    public string $confirmationFingerprint = '';

    public string $feedback = '';

    public string $feedbackType = 'success';

    public function mount(string $pageId, PublicationFingerprint $fingerprints): void
    {
        $this->pageId = $pageId;
        $this->confirmationFingerprint = $fingerprints->for($this->page());
    }

    #[Computed]
    public function page(): Page
    {
        return Page::query()->with([
            'currentDraftRevision.author', 'revisions.author', 'creator', 'updater',
            'publicationState.candidateRevision.author', 'publicationState.currentPublicRevision.author',
            'publicationState.submitter', 'publicationState.approver', 'publicationState.transitions.actor',
        ])->findOrFail($this->pageId);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function comparison(): array
    {
        $state = $this->page()->publicationState;
        $candidate = $state?->candidateRevision;
        if ($candidate === null) {
            return [];
        }
        $baseline = $state->currentPublicRevision
            ?? $this->page()->revisions->firstWhere('revision_number', $candidate->revision_number - 1);

        return app(RevisionComparison::class)->compare($this->page(), $candidate, $baseline);
    }

    /** @return array{blockers: list<string>, warnings: list<string>} */
    #[Computed]
    public function readiness(): array
    {
        $state = $this->page()->publicationState;
        $candidate = $state === null ? $this->page()->currentDraftRevision : ($state->candidateRevision ?? $this->page()->currentDraftRevision);

        return app(PublicationReadiness::class)->inspect($this->page(), $candidate);
    }

    public function preview(CreatePagePreviewUrl $action, ?string $revisionId = null): void
    {
        $revision = $revisionId === null ? $this->page()->currentDraftRevision : $this->page()->revisions->firstWhere('id', $revisionId);
        abort_unless($revision instanceof ContentRevision, 404);
        $this->dispatch('open-page-preview', url: $action->handle($this->actor(), $this->page(), $revision));
    }

    public function submit(SubmitPageForReview $action): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->page(), $this->workflowNote, $this->confirmationFingerprint, $this->confirmSupersession, $this->reason), 'Current draft submitted for review.');
    }

    public function requestChanges(RequestPageChanges $action): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->page(), $this->workflowNote), 'Changes requested. The submitted immutable revision remains recorded.');
    }

    public function approve(ApprovePageRevision $action): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->page(), $this->workflowNote, $this->confirmationFingerprint), 'Candidate revision approved.');
    }

    public function publish(PublishApprovedPageRevision $action): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->page(), $this->confirmationFingerprint), 'Revision designated as published. Public storefront projection is not active yet.');
    }

    public function schedule(ScheduleApprovedPageRevision $action): void
    {
        try {
            $local = Carbon::createFromFormat('Y-m-d\TH:i', $this->scheduleLocal, 'Africa/Dar_es_Salaam');
        } catch (\Throwable) {
            $this->error('Enter a valid Africa/Dar_es_Salaam schedule time.');

            return;
        }
        $this->run(fn () => $action->handle($this->actor(), $this->page(), $local->utc(), $this->confirmationFingerprint), 'Approved revision scheduled.');
    }

    public function cancelSchedule(CancelScheduledPagePublication $action): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->page(), $this->reason, $this->confirmationFingerprint), 'Schedule cancelled. Candidate returned to approved.');
    }

    public function unpublish(UnpublishPage $action): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->page(), $this->reason, $this->confirmationFingerprint), 'Designated published revision removed. Draft and history remain.');
    }

    public function archive(ArchivePage $action): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->page(), $this->reason), 'Page archived. Revisions and preview history remain available.');
    }

    public function restore(RestorePage $action): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->page(), $this->reason), 'Page restored to active draft.');
    }

    public function render(): View
    {
        return view('livewire.admin.content.pages.page-detail');
    }

    private function run(\Closure $transition, string $success): void
    {
        try {
            $transition();
            $this->feedback = $success;
            $this->feedbackType = 'success';
            $this->workflowNote = '';
            $this->reason = '';
            $this->confirmSupersession = false;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
        } finally {
            unset($this->page, $this->comparison, $this->readiness);
            $this->confirmationFingerprint = app(PublicationFingerprint::class)->for($this->page());
        }
    }

    private function error(string $message): void
    {
        $this->feedback = $message;
        $this->feedbackType = 'error';
    }

    private function actor(): User
    {
        return auth()->user() instanceof User ? auth()->user() : abort(403);
    }
}
