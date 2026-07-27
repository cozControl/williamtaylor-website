<?php

namespace App\Domain\PublicProjection\Data;

final readonly class PublicSiteChromeView
{
    /** @param list<PublicFooterGroupView>|null $footerGroups */
    public function __construct(
        public ?PublicNavigationView $navigation,
        public ?array $footerGroups,
        public ?PublicAnnouncementView $announcement,
        public ?PublicSiteProfileView $profile,
    ) {}

    public static function fallback(): self
    {
        return new self(null, null, null, null);
    }
}
