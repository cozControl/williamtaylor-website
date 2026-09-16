<?php

namespace App\Domain\Homepage\Support;

use App\Domain\Homepage\Models\HomepageHero;
use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;

final class HomepageDeliveryPresenter
{
    public function __construct(private ResolvePublicSiteChrome $site) {}

    /** @return array<string, string|null> */
    public function destinations(): array
    {
        $profile = $this->site->resolve()->profile;
        $email = $profile->email ?? '';
        $whatsapp = $profile->whatsApp ?? '';

        return [
            'contact_email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? 'mailto:'.$email : null,
            'contact_whatsapp' => preg_match('/^\+?[1-9][0-9]{7,14}$/', $whatsapp) ? 'https://wa.me/'.ltrim($whatsapp, '+') : null,
        ];
    }

    /** @return array<string, mixed> */
    public function present(): array
    {
        $data = [...HomepageHero::deliveryDefaults(), 'managed' => false, 'eligible' => false, 'url' => null];
        if (! app(HomepageRenderSnapshot::class)->hasColumns(['delivery_managed'])) {
            return $data;
        }
        $homepage = app(HomepageRenderSnapshot::class)->hero();
        if ($homepage === null) {
            return $data;
        }
        $data = [...$data, ...$homepage->only(array_keys(HomepageHero::deliveryDefaults()))];
        $url = $this->destinations()[$data['delivery_destination']] ?? null;
        $validCopy = true;
        foreach (['delivery_eyebrow', 'delivery_heading', 'delivery_cta_label'] as $key) {
            $value = $data[$key];
            $validCopy = $validCopy && is_string($value) && trim($value) !== '' && $value === strip_tags($value);
        }

        return [...$data, 'managed' => (bool) $data['delivery_managed'], 'eligible' => $url !== null && $validCopy, 'url' => $url];
    }
}
