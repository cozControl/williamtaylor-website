<?php

namespace App\Livewire\Admin\Media;

use App\Domain\Media\Actions\ConfirmUploadIntent;
use App\Domain\Media\Actions\CreateUploadIntent;
use App\Domain\Media\Contracts\MediaProvider;
use App\Domain\Media\Data\MediaConfirmationPayload;
use App\Domain\Media\Exceptions\ExactDuplicateMediaException;
use App\Domain\Media\Exceptions\MediaUploadFailure;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUploadIntent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

final class MediaLibrary extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $state = '';

    #[Url(except: '')]
    public string $usage = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function updated(): void
    {
        $this->resetPage();
    }

    /** @return array{endpoint: string, parameters: array<string, scalar>, expiresAt: int} */
    public function requestUploadIntent(string $resourceType, string $mimeType, int $bytes): array
    {
        $actor = $this->actor();
        $key = 'media-upload-intent:'.$actor->getKey().'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw new RuntimeException('Too many upload requests. Wait one minute before trying again.');
        }
        RateLimiter::hit($key, 60);
        $intent = app(CreateUploadIntent::class)->handle($actor, $resourceType, $mimeType, $bytes);

        return ['endpoint' => $intent->endpoint, 'parameters' => $intent->parameters, 'expiresAt' => $intent->expiresAt, 'reference' => $intent->reference];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function confirmUpload(array $payload, ?string $legacyTitle = null, ?string $legacyAltText = null, bool $legacyOverrideDuplicate = false, ?string $legacyOverrideReason = null): array
    {
        $intentReference = null;
        try {
            $confirmation = MediaConfirmationPayload::fromLivewire($payload, $legacyTitle, $legacyAltText, $legacyOverrideDuplicate, $legacyOverrideReason);
            $intentReference = $confirmation->intentReference;
            $asset = app(ConfirmUploadIntent::class)->handle(
                $this->actor(),
                $confirmation->intentReference,
                $confirmation->providerEvidence,
                $confirmation->title,
                $confirmation->altText,
                $confirmation->overrideDuplicate,
                $confirmation->overrideReason,
            );
        } catch (ExactDuplicateMediaException $exception) {
            $candidate = $exception->candidate->loadCount('usages');

            return ['status' => 'duplicate_detected', 'candidate' => [
                'id' => $candidate->getKey(),
                'title' => $candidate->internal_title,
                'resourceType' => $candidate->resource_type->value,
                'width' => $candidate->width,
                'height' => $candidate->height,
                'durationMs' => $candidate->duration_ms,
                'bytes' => $candidate->bytes,
                'state' => $candidate->state->value,
                'usageCount' => $candidate->usages_count,
                'url' => route('admin.media.show', $candidate),
            ]];
        } catch (RuntimeException $exception) {
            $reference = 'WT-'.strtoupper(substr((string) str()->ulid(), -8));
            Log::warning('Media upload confirmation failed.', [
                'failure_code' => $exception instanceof MediaUploadFailure ? $exception->failureCode : 'media.provider_unavailable',
                'intent_reference' => $intentReference,
                'support_reference' => $reference,
                'actor_id' => $this->actor()->getKey(),
                'provider' => (string) config('media.provider'),
                'occurred_at' => now('UTC')->toIso8601String(),
            ]);

            return [
                'status' => 'failed',
                'message' => "We could not finish processing this upload. Try again. If the problem continues, provide support reference {$reference}.",
                'reference' => $reference,
            ];
        }
        unset($this->assets);

        return ['status' => $asset->state->value === 'ready' ? 'completed' : 'processing', 'assetId' => $asset->getKey(), 'url' => route('admin.media.show', $asset)];
    }

    /** @return array{status: string, assetId: string, url: string} */
    public function reuseDuplicate(string $assetId, string $intentReference = ''): array
    {
        $actor = $this->actor();
        Gate::forUser($actor)->authorize('media.upload');
        $asset = MediaAsset::query()->findOrFail($assetId);
        MediaUploadIntent::query()
            ->whereKey($intentReference)
            ->where('actor_id', $actor->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now('UTC')]);

        return ['status' => 'completed', 'assetId' => $asset->getKey(), 'url' => route('admin.media.show', $asset)];
    }

    /** @return LengthAwarePaginator<int, MediaAsset> */
    #[Computed]
    public function assets(): LengthAwarePaginator
    {
        $search = mb_substr(trim($this->search), 0, 100);
        $sorts = ['newest' => ['created_at', 'desc'], 'oldest' => ['created_at', 'asc'], 'title' => ['internal_title', 'asc'], 'size' => ['bytes', 'desc']];
        [$column, $direction] = $sorts[$this->sort] ?? $sorts['newest'];

        return MediaAsset::query()->with('uploader:id,name')->withCount('usages')
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $s) => $s->where('internal_title', 'like', "%{$search}%")->orWhere('original_filename', 'like', "%{$search}%")->orWhere('default_alt_text', 'like', "%{$search}%")->orWhere('caption', 'like', "%{$search}%")->orWhere('credit', 'like', "%{$search}%")))
            ->when(in_array($this->type, ['image', 'video'], true), fn (Builder $q) => $q->where('resource_type', $this->type))
            ->when(in_array($this->state, ['ready', 'processing', 'failed', 'archived'], true), fn (Builder $q) => $q->where('state', $this->state))
            ->when($this->usage === 'used', fn (Builder $q) => $q->has('usages'))
            ->when($this->usage === 'unused', fn (Builder $q) => $q->doesntHave('usages'))
            ->orderBy($column, $direction)->orderBy('id')->paginate(18)->withQueryString();
    }

    public function thumbnailUrl(MediaAsset $asset): string
    {
        return app(MediaProvider::class)->deliveryUrl(
            $asset->provider_public_id,
            $asset->resource_type->value,
            'admin_thumbnail',
            $asset->focal_x === null ? null : (float) $asset->focal_x,
            $asset->focal_y === null ? null : (float) $asset->focal_y,
        );
    }

    public function providerConfigured(): bool
    {
        return config('media.provider') === 'deterministic'
            || (filled(config('media.cloudinary.cloud_name')) && filled(config('media.cloudinary.api_key')) && filled(config('media.cloudinary.api_secret')));
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    public function render(): View
    {
        return view('livewire.admin.media.media-library');
    }
}
