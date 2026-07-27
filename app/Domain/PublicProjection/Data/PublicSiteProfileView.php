<?php

namespace App\Domain\PublicProjection\Data;

final readonly class PublicSiteProfileView
{
    /** @param list<PublicSocialLinkView> $socialLinks */
    public function __construct(
        public string $brandName,
        public string $brandDescription,
        public ?string $headerLogoUrl,
        public ?string $footerLogoUrl,
        public string $email,
        public string $telephone,
        public string $whatsApp,
        public string $address,
        public string $businessHours,
        public array $socialLinks,
        public string $footerDescription,
        public string $copyright,
        public string $newsletterHeading,
        public string $newsletterCopy,
    ) {}
}
