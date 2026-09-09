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

final class UpdateHomepageHotSale
{
    public function __construct(private RecordAuditEvent $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, MediaAsset>  $assets
     */
    public function handle(User $actor, HomepageHero $homepage, array $data, array $assets): HomepageHero
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::SETTINGS_MANAGE);

        return DB::transaction(function () use ($actor, $homepage, $data, $assets): HomepageHero {
            $locked = HomepageHero::query()->lockForUpdate()->whereKey($homepage->id)->sole();
            if ($locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This Homepage changed after you opened it. Reload and try again.']);
            }
            $keys = array_keys(HomepageHero::hotSaleDefaults());
            $before = $locked->only($keys);
            $locked->forceFill([
                ...collect($data)->only($keys)->all(),
                'hot_sale_managed' => true,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            foreach (HomepageHero::HOT_SALE_MEDIA_ROLES as $position => $role) {
                MediaUsage::query()->where('owner_type', HomepageHero::class)->where('owner_identifier', $locked->id)->where('field_role', $role)->delete();
                MediaUsage::query()->create([
                    'id' => (string) Str::ulid(), 'media_asset_id' => $assets[$position]->id,
                    'owner_type' => HomepageHero::class, 'owner_identifier' => $locked->id,
                    'field_role' => $role,
                    'alt_text_override' => filled($data["hot_sale_tile_{$position}_alt_override"] ?? null) ? trim((string) $data["hot_sale_tile_{$position}_alt_override"]) : null,
                    'decorative_override' => false, 'sort_order' => $position - 1,
                ]);
            }

            $this->audit->handle('homepage.hot-sale.updated', $locked, $actor, $before, $locked->only($keys), PermissionRegistry::SETTINGS_MANAGE);

            return $locked->fresh();
        }, 3);
    }
}
