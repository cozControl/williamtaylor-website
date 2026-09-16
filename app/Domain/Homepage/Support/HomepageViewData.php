<?php

namespace App\Domain\Homepage\Support;

final class HomepageViewData
{
    /** @return array<string, mixed> */
    public function resolve(): array
    {
        return app(HomepageRenderSnapshot::class)->resolve(fn (): array => ['homepageVisibility' => app(HomepageSectionVisibility::class)->resolve(), 'homepageHiddenSections' => app(HomepageSectionVisibility::class)->hiddenRuntimeSections(), 'homepageClientStories' => app(HomepageClientStoriesPresenter::class)->present(), 'homepageHandbags' => app(HomepageHandbagsPresenter::class)->present(), 'homepageSummerEdit' => app(HomepageSummerEditPresenter::class)->present(), 'homepageDelivery' => app(HomepageDeliveryPresenter::class)->present(), 'homepageHero' => app(HomepageHeroPresenter::class)->present(), 'homepageNewArrivals' => app(HomepageNewArrivalsPresenter::class)->present(), 'homepageHotSale' => app(HomepageHotSalePresenter::class)->present(), 'homepageFutureStyle' => app(HomepageFutureStylePresenter::class)->present(), 'homepageLimitedEdition' => app(HomepageLimitedEditionPresenter::class)->present(), 'homepageExploreCollections' => app(HomepageExploreCollectionsPresenter::class)->present()]);
    }
}
