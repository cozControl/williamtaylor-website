<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Enums\AccessibilityClassification;
use App\Domain\Media\Models\MediaAsset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateMediaMetadata
{
    public function __construct(private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, MediaAsset $asset, array $data): void
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::MEDIA_EDIT);
        if (($data['focal_x'] ?? null) !== null && ((float) $data['focal_x'] < 0 || (float) $data['focal_x'] > 1)) {
            throw ValidationException::withMessages(['focal_x' => 'Focal point must be between 0 and 1.']);
        }
        $classification = AccessibilityClassification::from($data['accessibility_classification']);
        if ($classification === AccessibilityClassification::Informative && trim((string) ($data['default_alt_text'] ?? '')) === '') {
            throw ValidationException::withMessages(['default_alt_text' => 'Informative media requires meaningful alt text.']);
        }
        DB::transaction(function () use ($actor, $asset, $data, $classification): void {
            $before = $asset->only(['internal_title', 'default_alt_text', 'caption', 'credit', 'rights_source', 'focal_x', 'focal_y', 'accessibility_classification', 'is_decorative']);
            $asset->update(array_intersect_key([...$data, 'accessibility_classification' => $classification, 'is_decorative' => $classification === AccessibilityClassification::Decorative], array_flip(['internal_title', 'default_alt_text', 'caption', 'credit', 'rights_source', 'rights_notes', 'tags', 'collection_key', 'focal_x', 'focal_y', 'accessibility_classification', 'is_decorative'])));
            $this->audit->handle('media.asset.metadata-updated', $asset, $actor, $before, $asset->fresh()->only(array_keys($before)), PermissionRegistry::MEDIA_EDIT);
        });
    }
}
