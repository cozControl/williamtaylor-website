<?php

namespace App\Domain\Content\Support;

use Illuminate\Validation\Rule;

final class SlugRules
{
    /** @return list<mixed> */
    public static function for(string $locale, ?string $ignorePageId = null): array
    {
        return [
            'required',
            'string',
            'max:160',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::notIn(['admin', 'api', 'login', 'register', 'dashboard', 'preview', 'storage', 'livewire', 'settings']),
            Rule::unique('pages', 'slug')->where('locale', $locale)->ignore($ignorePageId),
        ];
    }
}
