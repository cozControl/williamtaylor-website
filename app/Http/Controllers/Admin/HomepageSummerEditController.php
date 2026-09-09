<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Catalogue\Models\Collection;
use App\Domain\Catalogue\Support\CollectionCardPresenter;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageSummerEditPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomepageSummerEditController
{
    public function edit(Request $request, HomepageSummerEditPresenter $presenter, CollectionCardPresenter $cards): View
    {
        $homepage = HomepageHero::firstOrCreate(['id' => HomepageHero::SINGLETON_ID], [...HomepageHero::defaults(), ...HomepageHero::summerEditDefaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $collections = Collection::query()->whereNull('archived_at')->where('catalogue_status', 'ready')->with(['currentDraftRevision', 'products.product.currentDraftRevision'])->orderBy('slug')->get()->map(fn (Collection $collection): array => $cards->present($collection))->filter(fn (array $card): bool => $card['eligible']);

        return view('admin.homepage.summer-edit', ['homepage' => $homepage, 'section' => $presenter->present(), 'collections' => $collections]);
    }

    public function update(Request $request, CollectionCardPresenter $cards, RecordAuditEvent $audit): RedirectResponse
    {
        $request->merge(['summer_edit_managed' => $request->boolean('summer_edit_managed')]);
        $rules = ['lock_version' => ['required', 'integer'], 'summer_edit_managed' => ['required', 'boolean']];
        foreach (['eyebrow' => 120, 'heading' => 160, 'copy_prefix' => 120, 'highlight' => 120, 'copy' => 500, 'cta_label' => 80] as $field => $max) {
            $rules['summer_edit_'.$field] = ['required', 'string', 'max:'.$max, 'not_regex:/[<>]/'];
        }
        $rules['summer_edit_collection_id'] = ['nullable', 'required_if:summer_edit_managed,true', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($cards): void {
            $collection = is_string($value) ? Collection::find($value) : null;
            if ($collection === null || ! $cards->present($collection)['eligible']) {
                $fail('Choose a visible Collection with a ready card image.');
            }
        }];
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $data, $audit): void {
            $homepage = HomepageHero::query()->lockForUpdate()->findOrFail(HomepageHero::SINGLETON_ID);
            if ($homepage->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This Homepage changed after you opened it. Reload and try again.']);
            }
            $keys = array_keys(HomepageHero::summerEditDefaults());
            $before = $homepage->only($keys);
            $values = [];
            foreach ($keys as $key) {
                $values[$key] = $data[$key] ?? null;
            }
            $homepage->forceFill([...$values, 'updated_by' => $request->user()->id, 'lock_version' => $homepage->lock_version + 1])->save();
            $audit->handle('homepage.summer_edit.updated', $homepage, $request->user(), $before, $homepage->only($keys));
        });

        return redirect()->route('admin.homepage.summer-edit.edit')->with('status', 'The Summer Edit updated successfully.');
    }
}
