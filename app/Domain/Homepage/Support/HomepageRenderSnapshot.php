<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Homepage\Models\HomepageHero;
use Illuminate\Support\Facades\Schema;

final class HomepageRenderSnapshot
{
    private const KEY = 'homepage.render_snapshot';

    /** @return array<string, mixed> */
    public function resolve(callable $render): array
    {
        $request = request();
        $request->attributes->set(self::KEY, ['columns' => Schema::getColumnListing('homepage_heroes')]);
        try {
            return $render();
        } finally {
            $request->attributes->remove(self::KEY);
        }
    }

    /** @param list<string> $columns */
    public function hasColumns(array $columns): bool
    {
        $snapshot = request()->attributes->get(self::KEY);

        return $snapshot === null
            ? Schema::hasColumns('homepage_heroes', $columns)
            : array_diff($columns, $snapshot['columns']) === [];
    }

    public function hero(): ?HomepageHero
    {
        $request = request();
        $snapshot = $request->attributes->get(self::KEY);
        if ($snapshot === null) {
            return Schema::hasTable('homepage_heroes') ? HomepageHero::find(HomepageHero::SINGLETON_ID) : null;
        }
        if (! array_key_exists('hero', $snapshot)) {
            $snapshot['hero'] = $snapshot['columns'] === [] ? null : HomepageHero::find(HomepageHero::SINGLETON_ID);
            $request->attributes->set(self::KEY, $snapshot);
        }

        return $snapshot['hero'];
    }
}
