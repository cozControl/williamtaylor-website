<?php

namespace App\Domain\Homepage\Actions;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Homepage\Models\HomepageHero;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class UpdateHomepageFutureStyle
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
            $before = $item->only(array_keys(HomepageHero::futureStyleDefaults()));
            $after = collect(HomepageHero::futureStyleDefaults())->mapWithKeys(fn ($default, string $key) => [$key => $key === 'future_style_managed' ? (bool) $data[$key] : ($data[$key] ?? $default)])->all();
            $item->forceFill([...$after, 'updated_by' => $actor->id, 'lock_version' => $item->lock_version + 1])->save();
            $this->audit->handle('homepage.future_style.updated', $item, $actor, $before, $after);
        }, 3);
    }
}
