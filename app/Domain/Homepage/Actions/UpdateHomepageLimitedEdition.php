<?php

namespace App\Domain\Homepage\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Homepage\Models\HomepageHero;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateHomepageLimitedEdition
{
    public function __construct(private RecordAuditEvent $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, HomepageHero $homepage, array $data): void
    {
        DB::transaction(function () use ($actor, $homepage, $data): void {
            $item = HomepageHero::query()->lockForUpdate()->findOrFail($homepage->id);
            if ((int) $data['lock_version'] !== $item->lock_version) {
                throw new InvalidArgumentException('Homepage changed while you were editing it. Refresh and try again.');
            }
            $keys = array_keys(HomepageHero::limitedEditionDefaults());
            $before = $item->only($keys);
            $after = collect(HomepageHero::limitedEditionDefaults())->mapWithKeys(fn ($default, string $key) => [$key => $key === 'limited_edition_managed' ? (bool) $data[$key] : ($data[$key] ?? $default)])->all();
            $item->forceFill([...$after, 'updated_by' => $actor->id, 'lock_version' => $item->lock_version + 1])->save();
            $this->audit->handle('homepage.limited_edition.updated', $item, $actor, $before, $after);
        }, 3);
    }
}
