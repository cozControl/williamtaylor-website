<?php

namespace App\Domain\PublicProjection\Data;

final readonly class PublicContactView
{
    public string $email;

    public string $telephone;

    public string $telephoneDisplay;

    public string $whatsAppUrl;

    public function __construct(?PublicSiteProfileView $profile)
    {
        $this->email = $profile?->email ?: 'hello@williamtaylor.co.tz';
        $telephone = $profile?->telephone ?: '+255724954876';
        $this->telephone = preg_replace('/[^+0-9]/', '', $telephone) ?? $telephone;
        $this->telephoneDisplay = preg_replace('/^(\+255)(\d{3})(\d{3})(\d{3})$/', '$1 $2 $3 $4', $this->telephone) ?? $telephone;
        $whatsApp = $profile?->whatsApp ?: '+255724954876';
        $this->whatsAppUrl = 'https://wa.me/'.preg_replace('/\D+/', '', $whatsApp);
    }
}
