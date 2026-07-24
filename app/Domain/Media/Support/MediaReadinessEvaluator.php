<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Models\MediaAsset;

final class MediaReadinessEvaluator
{
    /** @return array{ready: bool, failures: list<string>, warnings: list<string>} */
    public function evaluate(MediaAsset $asset): array
    {
        $failures = [];
        $warnings = [];
        if ($asset->provider_asset_id === '' || $asset->bytes < 1 || $asset->versions()->where('is_current', true)->count() !== 1) {
            $failures[] = 'Valid current provider version is required.';
        }
        if ($asset->accessibility_classification === AccessibilityClassification::Informative && trim((string) $asset->default_alt_text) === '') {
            $failures[] = 'Informative media requires default alt text.';
        }
        if (! $asset->caption) {
            $warnings[] = 'Caption is missing.';
        }
        if (! $asset->credit) {
            $warnings[] = 'Credit is missing.';
        }
        if (! $asset->rights_source) {
            $warnings[] = 'Rights source is missing.';
        }

        return ['ready' => $failures === [], 'failures' => $failures, 'warnings' => $warnings];
    }
}
