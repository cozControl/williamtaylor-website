<?php

namespace App\Domain\Homepage\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class UpdateHomepageExploreCollections
{
    public function __construct(private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, HomepageHero $homepage, array $data): HomepageHero
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::SETTINGS_MANAGE);

        return DB::transaction(function () use ($actor, $homepage, $data): HomepageHero {
            $locked = HomepageHero::query()->lockForUpdate()->whereKey($homepage->id)->sole();
            if ($locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This Homepage changed after you opened it. Reload and try again.']);
            }

            $keys = array_keys(HomepageHero::exploreCollectionsDefaults());
            $before = $locked->only($keys);
            $after = collect(HomepageHero::exploreCollectionsDefaults())
                ->mapWithKeys(fn ($default, string $key): array => [
                    $key => $key === 'explore_collections_managed'
                        ? (bool) $data[$key]
                        : ($data[$key] ?? $default),
                ])->all();

            $locked->forceFill([
                ...$after,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->audit->handle(
                'homepage.explore_collections.updated',
                $locked,
                $actor,
                $before,
                $after,
                PermissionRegistry::SETTINGS_MANAGE,
            );

            return $locked->fresh();
        }, 3);
    }
}
