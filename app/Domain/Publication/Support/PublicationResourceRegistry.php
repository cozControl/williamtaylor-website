<?php

namespace App\Domain\Publication\Support;

use App\Domain\Campaign\Models\CampaignRevision;
use App\Domain\Campaign\Support\CampaignReadinessEvaluator;
use App\Domain\Catalogue\Models\CollectionRevision;
use App\Domain\Catalogue\Models\ProductRevision;
use App\Domain\Catalogue\Support\CatalogueReadinessEvaluator;
use App\Domain\Catalogue\Support\CollectionReadinessEvaluator;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Identity\Support\PermissionRegistry;
use App\Domain\Publication\Data\PublicationResourceDefinition;
use App\Domain\PublicProjection\Services\ResolvePublicPage;
use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;
use App\Domain\Publishing\Models\PagePublicationState;
use App\Domain\Publishing\Support\PublicationReadiness;
use App\Domain\SiteContent\Models\SiteContentPublicationState;
use App\Domain\SiteContent\Support\SiteContentSchema;
use InvalidArgumentException;

final class PublicationResourceRegistry
{
    /** @return array<string, PublicationResourceDefinition> */
    public function all(): array
    {
        $publicModes = ['static', 'shadow', 'enabled', 'emergency_disabled'];
        $foundationModes = ['static'];

        return [
            'page' => new PublicationResourceDefinition('page', 'Content', ContentRevision::class, PublicationReadiness::class, PagePublicationState::class, ResolvePublicPage::class, 'frontend.about-static', 'public-page', $publicModes, true, true, 'sensitive', [PermissionRegistry::PAGES_PUBLISH, PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH], 'about-pilot'),
            'site_content' => new PublicationResourceDefinition('site_content', 'SiteContent', ContentRevision::class, SiteContentSchema::class, SiteContentPublicationState::class, ResolvePublicSiteChrome::class, 'static-site-chrome', 'public-site-content', $publicModes, true, true, 'sensitive', [PermissionRegistry::SETTINGS_PUBLISH, PermissionRegistry::PUBLICATION_EMERGENCY_UNPUBLISH], 'projection-capable'),
            'product' => new PublicationResourceDefinition('product', 'Catalogue', ProductRevision::class, CatalogueReadinessEvaluator::class, 'Product', null, 'five-static-product-routes', 'product-foundation', $foundationModes, false, false, 'internal', [], 'foundation-only'),
            'collection' => new PublicationResourceDefinition('collection', 'Catalogue', CollectionRevision::class, CollectionReadinessEvaluator::class, 'Collection', null, 'collections.index', 'collection-foundation', $foundationModes, false, false, 'internal', [], 'foundation-only'),
            'campaign' => new PublicationResourceDefinition('campaign', 'Campaign', CampaignRevision::class, CampaignReadinessEvaluator::class, 'Campaign', null, 'preorders-or-limited-edition', 'campaign-foundation', $foundationModes, false, false, 'sensitive', [], 'foundation-only'),
        ];
    }

    public function get(string $key): PublicationResourceDefinition
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException('Unsupported publication resource.');
    }
}
