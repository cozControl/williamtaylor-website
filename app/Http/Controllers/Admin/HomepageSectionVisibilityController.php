<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Homepage\Support\HomepageSectionRegistry;
use App\Domain\Homepage\Support\HomepageSectionVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class HomepageSectionVisibilityController
{
    public function __invoke(Request $request, string $section, HomepageSectionVisibility $visibility): RedirectResponse
    {
        abort_unless(array_key_exists($section, HomepageSectionRegistry::all()), 404);
        $request->validate(['is_visible' => ['required', 'boolean']]);
        $visible = $request->boolean('is_visible');
        $visibility->setVisible($request->user(), $section, $visible);

        return redirect()->route('admin.homepage.edit')->with('status', HomepageSectionRegistry::get($section)['title'].($visible ? ' is now visible.' : ' is now hidden. Configuration preserved.'));
    }
}
