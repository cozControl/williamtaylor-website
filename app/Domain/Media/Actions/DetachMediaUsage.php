<?php

namespace App\Domain\Media\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DetachMediaUsage
{
    public function __construct(private RecordAuditEvent $audit) {}

    public function handle(User $actor, MediaUsage $usage): void
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::MEDIA_EDIT);
        DB::transaction(function () use ($actor, $usage): void {
            $this->audit->handle('media.usage.detached', $usage, $actor, ['asset_id' => $usage->media_asset_id, 'owner_type' => $usage->owner_type], null, PermissionRegistry::MEDIA_EDIT);
            $usage->delete();
        });
    }
}
