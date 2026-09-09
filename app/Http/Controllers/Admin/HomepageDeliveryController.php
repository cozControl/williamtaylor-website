<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Actions\RecordAuditEvent;
use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\Homepage\Support\HomepageDeliveryPresenter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class HomepageDeliveryController
{
    public function edit(Request $request, HomepageDeliveryPresenter $presenter): View
    {
        $homepage = HomepageHero::firstOrCreate(['id' => HomepageHero::SINGLETON_ID], [...HomepageHero::defaults(), ...HomepageHero::deliveryDefaults(), 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);

        return view('admin.homepage.delivery', ['homepage' => $homepage, 'section' => $presenter->present(), 'destinations' => $presenter->destinations()]);
    }

    public function update(Request $request, HomepageDeliveryPresenter $presenter, RecordAuditEvent $audit): RedirectResponse
    {
        $request->merge(['delivery_managed' => $request->boolean('delivery_managed')]);
        $data = $request->validate([
            'lock_version' => ['required', 'integer'], 'delivery_managed' => ['required', 'boolean'],
            'delivery_eyebrow' => ['required', 'string', 'max:120', 'not_regex:/[<>]/'],
            'delivery_heading' => ['required', 'string', 'max:160', 'not_regex:/[<>]/'],
            'delivery_cta_label' => ['required', 'string', 'max:80', 'not_regex:/[<>]/'],
            'delivery_destination' => ['required', Rule::in(['contact_email', 'contact_whatsapp'])],
        ]);
        if ($data['delivery_managed'] && ($presenter->destinations()[$data['delivery_destination']] ?? null) === null) {
            throw ValidationException::withMessages(['delivery_destination' => 'Publish the selected contact detail in Site Settings before enabling this feature.']);
        }
        DB::transaction(function () use ($data, $request, $audit): void {
            $homepage = HomepageHero::query()->lockForUpdate()->findOrFail(HomepageHero::SINGLETON_ID);
            if ($homepage->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This Homepage changed after you opened it. Reload and try again.']);
            }
            $keys = array_keys(HomepageHero::deliveryDefaults());
            $before = $homepage->only($keys);
            $values = [];
            foreach ($keys as $key) {
                $values[$key] = $data[$key];
            }
            $homepage->forceFill([...$values, 'updated_by' => $request->user()->id, 'lock_version' => $homepage->lock_version + 1])->save();
            $audit->handle('homepage.delivery.updated', $homepage, $request->user(), $before, $homepage->only($keys));
        });

        return redirect()->route('admin.homepage.delivery.edit')->with('status', 'Complimentary Delivery updated successfully.');
    }
}
