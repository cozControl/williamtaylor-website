<?php

namespace App\Livewire\Admin\Media;

use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Actions\ArchiveMediaAsset;
use App\Domain\Media\Actions\CreateUploadIntent;
use App\Domain\Media\Actions\ReplaceMediaAsset;
use App\Domain\Media\Actions\RestoreMediaAsset;
use App\Domain\Media\Actions\UpdateMediaMetadata;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Support\MediaReadinessEvaluator;
use App\Domain\Media\Support\ReplacementProposalStore;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use RuntimeException;

final class MediaDetail extends Component
{
    public string $assetId;

    public string $internalTitle = '';

    public string $defaultAltText = '';

    public string $caption = '';

    public string $credit = '';

    public string $rightsSource = '';

    public string $rightsNotes = '';

    public string $accessibilityClassification = 'informative';

    public ?float $focalX = null;

    public ?float $focalY = null;

    public string $reason = '';

    public string $feedback = '';

    public string $feedbackType = '';

    /** @var array<string, mixed> */
    public array $replacementProposal = [];

    public bool $replacementConfirmed = false;

    public function mount(string $assetId): void
    {
        $this->assetId = $assetId;
        $a = $this->asset();
        $this->internalTitle = $a->internal_title;
        $this->defaultAltText = (string) $a->default_alt_text;
        $this->caption = (string) $a->caption;
        $this->credit = (string) $a->credit;
        $this->rightsSource = (string) $a->rights_source;
        $this->rightsNotes = (string) $a->rights_notes;
        $this->accessibilityClassification = $a->accessibility_classification->value;
        $this->focalX = $a->focal_x === null ? null : (float) $a->focal_x;
        $this->focalY = $a->focal_y === null ? null : (float) $a->focal_y;
    }

    #[Computed]
    public function asset(): MediaAsset
    {
        return MediaAsset::query()->with(['versions', 'usages', 'uploader'])->findOrFail($this->assetId);
    }

    public function save(UpdateMediaMetadata $action): void
    {
        $this->validate(['internalTitle' => ['required', 'string', 'max:255'], 'defaultAltText' => ['nullable', 'string', 'max:1000'], 'accessibilityClassification' => ['required', Rule::in(['informative', 'decorative', 'complex', 'text_image'])], 'focalX' => ['nullable', 'numeric', 'between:0,1'], 'focalY' => ['nullable', 'numeric', 'between:0,1']]);
        $action->handle($this->actor(), $this->asset(), ['internal_title' => $this->internalTitle, 'default_alt_text' => $this->defaultAltText, 'caption' => $this->caption, 'credit' => $this->credit, 'rights_source' => $this->rightsSource, 'rights_notes' => $this->rightsNotes, 'accessibility_classification' => $this->accessibilityClassification, 'focal_x' => $this->focalX, 'focal_y' => $this->focalY]);
        unset($this->asset);
        $this->success('Media metadata was updated and audited.');
    }

    /** @return array{endpoint: string, parameters: array<string, scalar>, expiresAt: int} */
    public function requestReplacementIntent(string $mimeType, int $bytes): array
    {
        Gate::forUser($this->actor())->authorize(PermissionRegistry::MEDIA_REPLACE);
        $intent = app(CreateUploadIntent::class)->handle($this->actor(), $this->asset()->resource_type->value, $mimeType, $bytes);

        return ['endpoint' => $intent->endpoint, 'parameters' => $intent->parameters, 'expiresAt' => $intent->expiresAt];
    }

    /** @param array<string, mixed> $providerResult */
    public function prepareReplacement(array $providerResult, MediaProvider $provider, ReplacementProposalStore $store): void
    {
        Gate::forUser($this->actor())->authorize(PermissionRegistry::MEDIA_REPLACE);
        $verified = $provider->verifyUploadResult($providerResult);
        if ($verified->resourceType !== $this->asset()->resource_type->value) {
            throw new RuntimeException('Replacement must use the same image or video resource type.');
        }
        $proposal = $store->put($this->actor(), $this->asset(), $verified);
        $this->replacementProposal = get_object_vars($proposal);
        $this->replacementConfirmed = false;
        $this->feedback = '';
    }

    public function applyReplacement(ReplaceMediaAsset $action, ReplacementProposalStore $store): void
    {
        $this->validate(['reason' => ['required', 'string', 'max:2000'], 'replacementConfirmed' => ['accepted']]);
        try {
            $action->handleProposal($this->actor(), $this->asset(), (string) $this->replacementProposal['token'], $this->reason);
        } catch (DomainException) {
            $stored = $store->pull($this->actor(), $this->asset(), (string) $this->replacementProposal['token']);
            $proposal = $store->put($this->actor(), $this->asset()->fresh(), $stored['verified']);
            $this->replacementProposal = get_object_vars($proposal);
            $this->replacementConfirmed = false;
            $this->error('Media usage or version state changed. Review the updated replacement comparison and confirm again.');
            $this->dispatch('media-replacement-stale');

            return;
        }
        unset($this->asset);
        $this->replacementProposal = [];
        $this->replacementConfirmed = false;
        $this->reason = '';
        $this->success('Media replacement was committed and audited. The previous provider asset remains retained.');
    }

    public function cancelReplacement(ReplacementProposalStore $store): void
    {
        if (isset($this->replacementProposal['token'])) {
            $store->forget((string) $this->replacementProposal['token']);
        }
        $this->replacementProposal = [];
        $this->replacementConfirmed = false;
        $this->success('Replacement was cancelled. The unaccepted provider upload is eligible for reconciliation cleanup.');
        $this->dispatch('media-replacement-cancelled');
    }

    public function archive(ArchiveMediaAsset $action): void
    {
        $action->handle($this->actor(), $this->asset(), $this->reason);
        unset($this->asset);
        $this->success('Media was archived. Existing usages remain intact.');
    }

    public function restore(RestoreMediaAsset $action): void
    {
        $action->handle($this->actor(), $this->asset());
        unset($this->asset);
        $this->success('Media was restored after provider verification.');
    }

    private function success(string $message): void
    {
        $this->feedback = $message;
        $this->feedbackType = 'success';
    }

    private function error(string $message): void
    {
        $this->feedback = $message;
        $this->feedbackType = 'error';
    }

    private function actor(): User
    {
        $u = auth()->user();
        abort_unless($u instanceof User, 403);

        return $u;
    }

    public function render(MediaProvider $provider, MediaReadinessEvaluator $readiness): View
    {
        return view('livewire.admin.media.media-detail', ['previewUrl' => $provider->deliveryUrl($this->asset()->provider_public_id, $this->asset()->resource_type->value, 'admin_preview', $this->focalX, $this->focalY), 'readiness' => $readiness->evaluate($this->asset())]);
    }
}
