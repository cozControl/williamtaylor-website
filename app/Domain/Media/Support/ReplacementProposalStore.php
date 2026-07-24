<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Data\ReplacementProposal;
use App\Domain\Media\Data\VerifiedProviderAsset;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

final class ReplacementProposalStore
{
    public function put(User $actor, MediaAsset $asset, VerifiedProviderAsset $verified): ReplacementProposal
    {
        $token = (string) Str::ulid();
        $fingerprint = app(ReplacementFingerprint::class)->for($asset, $verified);
        $current = $asset->versions()->where('is_current', true)->firstOrFail();
        $currentFacts = ['version' => $current->version_number, 'format' => $current->format, 'width' => $current->width, 'height' => $current->height, 'duration_ms' => $current->duration_ms, 'bytes' => $current->bytes];
        $proposedFacts = ['format' => $verified->format, 'width' => $verified->width, 'height' => $verified->height, 'duration_ms' => $verified->durationMs, 'bytes' => $verified->bytes];
        $warnings = $this->warnings($currentFacts, $proposedFacts);
        Cache::put($this->key($token), [
            'actor_id' => $actor->getKey(),
            'asset_id' => $asset->getKey(),
            'fingerprint' => $fingerprint,
            'verified' => get_object_vars($verified),
        ], now()->addMinutes(10));

        return new ReplacementProposal($token, $fingerprint, $currentFacts, $proposedFacts, $warnings, $asset->usages()->count());
    }

    /** @return array{fingerprint: string, verified: VerifiedProviderAsset} */
    public function pull(User $actor, MediaAsset $asset, string $token): array
    {
        $stored = Cache::get($this->key($token));
        if (! is_array($stored) || $stored['actor_id'] !== $actor->getKey() || $stored['asset_id'] !== $asset->getKey()) {
            throw new RuntimeException('The replacement proposal expired. Upload the replacement again.');
        }
        $verified = new VerifiedProviderAsset(...$stored['verified']);

        return ['fingerprint' => $stored['fingerprint'], 'verified' => $verified];
    }

    public function forget(string $token): void
    {
        Cache::forget($this->key($token));
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $proposed
     * @return list<string>
     */
    private function warnings(array $current, array $proposed): array
    {
        $warnings = [];
        if ($current['format'] !== $proposed['format']) {
            $warnings[] = 'File format changes.';
        }
        if ($current['width'] && $proposed['width'] && abs($proposed['width'] - $current['width']) / $current['width'] > .2) {
            $warnings[] = 'Width changes materially.';
        }
        if ($current['height'] && $proposed['height'] && abs($proposed['height'] - $current['height']) / $current['height'] > .2) {
            $warnings[] = 'Height changes materially.';
        }
        if ($current['bytes'] && abs($proposed['bytes'] - $current['bytes']) / $current['bytes'] > .5) {
            $warnings[] = 'File size changes materially.';
        }
        $currentRatio = $current['width'] && $current['height'] ? $current['width'] / $current['height'] : null;
        $proposedRatio = $proposed['width'] && $proposed['height'] ? $proposed['width'] / $proposed['height'] : null;
        if ($currentRatio && $proposedRatio && abs($proposedRatio - $currentRatio) / $currentRatio > .1) {
            $warnings[] = 'Aspect ratio changes and existing crops need review.';
        }

        return $warnings;
    }

    private function key(string $token): string
    {
        return 'media-replacement-proposal:'.$token;
    }
}
