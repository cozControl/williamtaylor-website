<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Models\HomepageSectionSetting;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

final class HomepageSectionVisibility
{
    private const REQUEST_KEY = 'homepage.section_visibility';

    /** @return array<string, bool> */
    public function resolve(): array
    {
        $request = request();
        if ($request->attributes->has(self::REQUEST_KEY)) {
            return $request->attributes->get(self::REQUEST_KEY);
        }
        $visibility = array_map(fn (array $section): bool => $section['default_visible'], HomepageSectionRegistry::all());
        if (Schema::hasTable('homepage_section_settings')) {
            foreach (HomepageSectionSetting::where('homepage_hero_id', HomepageHero::SINGLETON_ID)->get() as $setting) {
                if (array_key_exists($setting->section_key, $visibility)) {
                    $visibility[$setting->section_key] = $setting->is_visible;
                }
            }
        }
        $request->attributes->set(self::REQUEST_KEY, $visibility);

        return $visibility;
    }

    public function isVisible(string $section): bool
    {
        HomepageSectionRegistry::get($section);

        return $this->resolve()[$section];
    }

    public function setVisible(User $actor, string $section, bool $visible): void
    {
        Gate::forUser($actor)->authorize(PermissionRegistry::SETTINGS_MANAGE);
        $definition = HomepageSectionRegistry::get($section);
        DB::transaction(function () use ($actor, $section, $visible, $definition): void {
            $homepage = HomepageHero::query()->lockForUpdate()->findOrFail(HomepageHero::SINGLETON_ID);
            $setting = HomepageSectionSetting::where('homepage_hero_id', $homepage->id)->where('section_key', $section)->first();
            $before = $setting === null ? $definition['default_visible'] : $setting->is_visible;
            if ($before === $visible) {
                return;
            }
            $setting = HomepageSectionSetting::updateOrCreate(['homepage_hero_id' => $homepage->id, 'section_key' => $section], ['is_visible' => $visible]);
            app(RecordAuditEvent::class)->handle('homepage.section_visibility.updated', $setting, $actor, ['section' => $section, 'is_visible' => $before], ['section' => $section, 'is_visible' => $visible], PermissionRegistry::SETTINGS_MANAGE);
        });
        request()->attributes->remove(self::REQUEST_KEY);
    }

    /** @return array<string, array{marker: string, heading: string, heading_selector: string, boundary: string}> */
    public function hiddenRuntimeSections(): array
    {
        $hidden = [];
        foreach (HomepageSectionRegistry::all() as $key => $section) {
            if (! $this->isVisible($key)) {
                $hidden[$key] = ['marker' => $section['marker'], 'heading' => $section['heading'], 'heading_selector' => $section['heading_selector'], 'boundary' => $section['boundary']];
            }
        }

        return $hidden;
    }
}
