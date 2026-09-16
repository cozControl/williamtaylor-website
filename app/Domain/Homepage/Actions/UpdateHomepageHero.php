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
    public function handle(User $actor, HomepageHero $hero, array $data, ?MediaAsset $asset, ?MediaAsset $mobileAsset = null): HomepageHero
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::SETTINGS_MANAGE);

        return DB::transaction(function () use ($actor, $hero, $data, $asset, $mobileAsset): HomepageHero {
            $locked = HomepageHero::query()->lockForUpdate()->whereKey($hero->id)->sole();
            if ($locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This Homepage Hero changed after you opened it. Reload and try again.']);
            }

            $before = $locked->only(array_keys(HomepageHero::defaults()));
            $before['media'] = MediaUsage::query()->where('owner_type', HomepageHero::class)->where('owner_identifier', $locked->id)
                ->whereIn('field_role', [HomepageHero::MEDIA_ROLE, HomepageHero::MOBILE_MEDIA_ROLE])->pluck('media_asset_id', 'field_role')->all();
            $locked->forceFill([
                ...collect($data)->only(array_keys(HomepageHero::defaults()))->all(),
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $assets = [HomepageHero::MEDIA_ROLE => $asset];
            // Legacy callers omit the new field; only an explicit empty value removes it.
            if (array_key_exists('mobile_background_media_id', $data)) {
                $assets[HomepageHero::MOBILE_MEDIA_ROLE] = $mobileAsset;
            }
            foreach ($assets as $role => $image) {
                $usage = MediaUsage::query()->where('owner_type', HomepageHero::class)
                    ->where('owner_identifier', $locked->id)
                    ->where('field_role', $role)
                    ->first();
                if ($usage !== null && $usage->media_asset_id !== $image?->id) {
                    $usage->delete();
                    $usage = null;
                }
                if ($image !== null && $usage === null) {
                    MediaUsage::query()->create([
                        'id' => (string) Str::ulid(),
                        'media_asset_id' => $image->id,
                        'owner_type' => HomepageHero::class,
                        'owner_identifier' => $locked->id,
                        'field_role' => $role,
                        'decorative_override' => true,
                        'sort_order' => 0,
                    ]);
                }
            }

            $after = $locked->only(array_keys(HomepageHero::defaults()));
            $after['media'] = MediaUsage::query()->where('owner_type', HomepageHero::class)->where('owner_identifier', $locked->id)
                ->whereIn('field_role', [HomepageHero::MEDIA_ROLE, HomepageHero::MOBILE_MEDIA_ROLE])->pluck('media_asset_id', 'field_role')->all();
            $this->audit->handle('homepage.hero.updated', $locked, $actor, $before, $after, PermissionRegistry::SETTINGS_MANAGE);

            return $locked->fresh();
        }, 3);
    }
}
