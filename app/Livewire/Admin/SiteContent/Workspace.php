<?php

namespace App\Livewire\Admin\SiteContent;

use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Enums\MediaAssetState;
use App\Domain\Media\Enums\MediaResourceType;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\SiteContent\Actions\ArchiveAnnouncement;
use App\Domain\SiteContent\Actions\RestoreAnnouncement;
use App\Domain\SiteContent\Actions\SaveSiteContentDraft;
use App\Domain\SiteContent\Models\SiteContent;
use App\Domain\SiteContent\Services\SiteContentWorkflow;
use App\Domain\SiteContent\Support\SiteContentFingerprint;
use App\Domain\SiteContent\Support\SiteContentTypeRegistry;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Component;
use Throwable;

final class Workspace extends Component
{
    public string $siteContentId = '';

    public string $expectedRevisionId = '';

    /** @var array<string, mixed> */
    public array $content = [];

    public string $changeSummary = '';

    public string $workflowNote = '';

    public string $reason = '';

    public string $scheduleLocal = '';

    public bool $confirmSupersession = false;

    public string $fingerprint = '';

    public string $feedback = '';

    public string $feedbackType = 'success';

    public function mount(SiteContent $siteContent, SiteContentFingerprint $fingerprints): void
    {
        $this->siteContentId = $siteContent->getKey();
        Gate::forUser($this->actor())->authorize(app(SiteContentTypeRegistry::class)->get($siteContent->type)->permission('view'));
        $this->reload($fingerprints);
    }

    public function save(SaveSiteContentDraft $action, SiteContentWorkflow $workflow, SiteContentFingerprint $fingerprints): void
    {
        $this->run(function () use ($action, $workflow): void {
            $revision = DB::transaction(function () use ($action, $workflow) {
                $content = $this->siteContent();
                $revision = $action->handle($this->actor(), $content, $this->expectedRevisionId, $this->content, $this->changeSummary ?: 'Saved from the website editor');
                $workflow->makeCurrentDraftEffective($this->actor(), $content->fresh());

                return $revision;
            }, 3);
            $this->expectedRevisionId = $revision->getKey();
            $this->content = $revision->payload;
            $this->changeSummary = '';
        }, match ($this->siteContent()->type) {
            SiteContentTypeRegistry::PRIMARY_NAVIGATION, SiteContentTypeRegistry::FOOTER_NAVIGATION => 'Navigation updated.',
            SiteContentTypeRegistry::ANNOUNCEMENT => 'Announcement saved.',
            default => 'Site settings saved. Changes are now published on the website.',
        }, $fingerprints);
    }

    public function submit(SiteContentWorkflow $workflow, SiteContentFingerprint $fingerprints): void
    {
        $this->run(fn () => $workflow->submit($this->actor(), $this->siteContent(), $this->workflowNote, $this->fingerprint, $this->confirmSupersession, $this->reason), 'Submitted for review.', $fingerprints);
    }

    public function requestChanges(SiteContentWorkflow $workflow, SiteContentFingerprint $fingerprints): void
    {
        $this->run(fn () => $workflow->requestChanges($this->actor(), $this->siteContent(), $this->workflowNote), 'Changes requested.', $fingerprints);
    }

    public function approve(SiteContentWorkflow $workflow, SiteContentFingerprint $fingerprints): void
    {
        $this->run(fn () => $workflow->approve($this->actor(), $this->siteContent(), $this->workflowNote, $this->fingerprint), 'Candidate approved.', $fingerprints);
    }

    public function publish(SiteContentWorkflow $workflow, SiteContentFingerprint $fingerprints): void
    {
        $this->run(fn () => $workflow->publish($this->actor(), $this->siteContent(), $this->fingerprint), 'Changes published. '.(config('public_site_content.enabled') ? 'They are available to the live website.' : 'Website publishing is currently paused.'), $fingerprints);
    }

    public function cancelSchedule(SiteContentWorkflow $workflow, SiteContentFingerprint $fingerprints): void
    {
        $this->run(fn () => $workflow->cancelSchedule($this->actor(), $this->siteContent(), $this->reason, $this->fingerprint), 'Schedule cancelled.', $fingerprints);
    }

    public function unpublish(SiteContentWorkflow $workflow, SiteContentFingerprint $fingerprints): void
    {
        $this->run(fn () => $workflow->unpublish($this->actor(), $this->siteContent(), $this->reason, $this->fingerprint), 'Published designation removed.', $fingerprints);
    }

    public function schedule(SiteContentWorkflow $workflow, SiteContentFingerprint $fingerprints): void
    {
        $this->run(function () use ($workflow): void {
            $local = Carbon::createFromFormat('Y-m-d\TH:i', $this->scheduleLocal, 'Africa/Dar_es_Salaam');
            $workflow->schedule($this->actor(), $this->siteContent(), $local->utc(), $this->fingerprint);
        }, 'Approved revision scheduled.', $fingerprints);
    }

    public function archive(ArchiveAnnouncement $action, SiteContentFingerprint $fingerprints): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->siteContent(), $this->reason), 'Announcement archived.', $fingerprints);
    }

    public function restore(RestoreAnnouncement $action, SiteContentFingerprint $fingerprints): void
    {
        $this->run(fn () => $action->handle($this->actor(), $this->siteContent(), $this->reason), 'Announcement restored.', $fingerprints);
    }

    public function addPrimaryItem(): void
    {
        $this->content['items'][] = ['key' => 'item-'.(count($this->content['items']) + 1), 'label' => '', 'link' => ['type' => 'internal_path', 'value' => '/'], 'new_tab' => false, 'visibility' => 'all', 'children' => []];
    }

    public function addPrimaryChild(int $parent): void
    {
        $this->content['items'][$parent]['children'][] = ['key' => 'child-'.($parent + 1).'-'.(count($this->content['items'][$parent]['children']) + 1), 'label' => '', 'link' => ['type' => 'internal_path', 'value' => '/'], 'new_tab' => false, 'visibility' => 'all'];
    }

    public function removePrimaryItem(int $index): void
    {
        array_splice($this->content['items'], $index, 1);
    }

    public function movePrimaryItem(int $index, int $direction): void
    {
        $target = $index + $direction;
        if ($target < 0 || $target >= count($this->content['items'])) {
            return;
        } [$this->content['items'][$index], $this->content['items'][$target]] = [$this->content['items'][$target], $this->content['items'][$index]];
    }

    public function addFooterLink(int $group): void
    {
        $this->content['groups'][$group]['links'][] = ['key' => 'link-'.($group + 1).'-'.(count($this->content['groups'][$group]['links']) + 1), 'label' => '', 'link' => ['type' => 'internal_path', 'value' => '/'], 'new_tab' => false];
    }

    public function removeFooterLink(int $group, int $index): void
    {
        array_splice($this->content['groups'][$group]['links'], $index, 1);
    }

    public function addSocial(): void
    {
        $this->content['social_links'][] = ['platform' => 'instagram', 'url' => 'https://', 'label' => 'Instagram'];
    }

    public function removeSocial(int $index): void
    {
        array_splice($this->content['social_links'], $index, 1);
    }

    public function preview(): void
    {
        $content = $this->siteContent();
        Gate::forUser($this->actor())->authorize(app(SiteContentTypeRegistry::class)->get($content->type)->permission('preview'));
        $url = URL::temporarySignedRoute('preview.site-content.show', now()->addMinutes(15), ['siteContentResource' => $content, 'revision' => $this->expectedRevisionId]);
        $this->dispatch('open-site-content-preview', url: $url);
    }

    public function render(): View
    {
        $content = $this->siteContent();
        $definition = app(SiteContentTypeRegistry::class)->get($content->type);
        $state = $content->publicationState;
        $before = $state?->currentPublicRevision->payload ?? [];
        $after = $state?->candidateRevision->payload ?? $content->currentDraftRevision->payload;

        $readyMedia = collect();
        if ($content->type === SiteContentTypeRegistry::SITE_PROFILE) {
            $provider = app(MediaProvider::class);
            $readyMedia = MediaAsset::query()
                ->where('state', MediaAssetState::Ready->value)
                ->where('resource_type', MediaResourceType::Image->value)
                ->whereNotNull('confirmed_at')
                ->whereNull('archived_at')
                ->orderBy('original_filename')
                ->get()
                ->filter(fn (MediaAsset $asset): bool => $asset->is_decorative || (filled($asset->default_alt_text) && strip_tags((string) $asset->default_alt_text) === $asset->default_alt_text))
                ->map(fn (MediaAsset $asset): array => [
                    'id' => (string) $asset->getKey(),
                    'filename' => $asset->original_filename,
                    'width' => $asset->width,
                    'height' => $asset->height,
                    'alt' => $asset->is_decorative ? 'Decorative image' : (string) $asset->default_alt_text,
                    'thumbnail' => $provider->deliveryUrl($asset->provider_public_id, $asset->resource_type->value, 'admin_thumbnail', $asset->focal_x ? (float) $asset->focal_x : null, $asset->focal_y ? (float) $asset->focal_y : null),
                ]);
        }

        return view('livewire.admin.site-content.workspace', compact('content', 'definition', 'readyMedia') + ['siteContent' => $content, 'comparison' => $definition->compare($before, $after)]);
    }

    private function reload(SiteContentFingerprint $fingerprints): void
    {
        $content = $this->siteContent();
        $this->expectedRevisionId = $content->currentDraftRevision->getKey();
        $this->content = $content->currentDraftRevision->payload;
        $this->fingerprint = $fingerprints->for($content);
    }

    private function siteContent(): SiteContent
    {
        return SiteContent::query()->with(['currentDraftRevision', 'revisions.author', 'publicationState.candidateRevision', 'publicationState.currentPublicRevision', 'publicationState.transitions.actor'])->findOrFail($this->siteContentId);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function run(callable $operation, string $success, SiteContentFingerprint $fingerprints): void
    {
        try {
            $operation();
            $this->feedback = $success;
            $this->feedbackType = 'success';
            $this->workflowNote = '';
            $this->reason = '';
            $this->confirmSupersession = false;
            $this->reload($fingerprints);
        } catch (Throwable $exception) {
            $this->feedback = $exception->getMessage();
            $this->feedbackType = 'error';
        }
    }
}
