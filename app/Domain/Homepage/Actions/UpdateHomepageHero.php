<?php

namespace App\Domain\Homepage\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Media\Models\MediaAsset;
use App\Domain\Media\Models\MediaUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UpdateHomepageHero
{
    public function __construct(private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, HomepageHero $hero, array $data, ?MediaAsset $asset): HomepageHero
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::SETTINGS_MANAGE);

        return DB::transaction(function () use ($actor, $hero, $data, $asset): HomepageHero {
            $locked = HomepageHero::query()->lockForUpdate()->whereKey($hero->id)->sole();
            if ($locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This Homepage Hero changed after you opened it. Reload and try again.']);
            }

            $before = $locked->only(array_keys(HomepageHero::defaults()));
            $locked->forceFill([
                ...collect($data)->only(array_keys(HomepageHero::defaults()))->all(),
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $usage = MediaUsage::query()->where('owner_type', HomepageHero::class)
                ->where('owner_identifier', $locked->id)
                ->where('field_role', HomepageHero::MEDIA_ROLE)
                ->first();
            if ($usage !== null && $usage->media_asset_id !== $asset?->id) {
                $usage->delete();
                $usage = null;
            }
            if ($asset !== null && $usage === null) {
                MediaUsage::query()->create([
                    'id' => (string) Str::ulid(),
                    'media_asset_id' => $asset->id,
                    'owner_type' => HomepageHero::class,
                    'owner_identifier' => $locked->id,
                    'field_role' => HomepageHero::MEDIA_ROLE,
                    'decorative_override' => true,
                    'sort_order' => 0,
                ]);
            }

            $this->audit->handle('homepage.hero.updated', $locked, $actor, $before, $locked->only(array_keys(HomepageHero::defaults())), PermissionRegistry::SETTINGS_MANAGE);

            return $locked->fresh();
        }, 3);
    }
}
