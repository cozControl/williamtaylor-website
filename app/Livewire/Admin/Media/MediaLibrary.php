<?php

namespace App\Livewire\Admin\Media;

use App\Domain\Media\Actions\ConfirmUploadedAsset;
use App\Domain\Media\Actions\CreateUploadIntent;
use App\Domain\Media\Exceptions\ExactDuplicateMediaException;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
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

        return ['endpoint' => $intent->endpoint, 'parameters' => $intent->parameters, 'expiresAt' => $intent->expiresAt];
    }

    /**
     * @param  array<string, mixed>  $providerResult
     * @return array<string, mixed>
     */
    public function confirmUpload(array $providerResult, string $title, ?string $altText = null, bool $overrideDuplicate = false, ?string $overrideReason = null): array
    {
        try {
            $asset = app(ConfirmUploadedAsset::class)->handle($this->actor(), $providerResult, $title, $altText, $overrideDuplicate, $overrideReason);
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
        } catch (RuntimeException) {
            return [
                'status' => 'failed',
                'message' => 'Secure provider confirmation failed. Retry confirmation or reselect the file.',
            ];
        }
        unset($this->assets);

        return ['status' => $asset->state->value === 'ready' ? 'completed' : 'processing', 'assetId' => $asset->getKey(), 'url' => route('admin.media.show', $asset)];
    }

    /** @return array{status: string, assetId: string, url: string} */
    public function reuseDuplicate(string $assetId): array
    {
        Gate::forUser($this->actor())->authorize('media.upload');
        $asset = MediaAsset::query()->findOrFail($assetId);

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
            ->orderBy($column, $direction)->orderBy('id')->paginate(18)->withQueryString();
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
