<?php

namespace App\Http\Controllers;

use App\Domain\Homepage\Support\HomepageClientStoriesPresenter;
use App\Domain\Homepage\Support\HomepageDeliveryPresenter;
use App\Domain\Homepage\Support\HomepageExploreCollectionsPresenter;
use App\Domain\Homepage\Support\HomepageFutureStylePresenter;
use App\Domain\Homepage\Support\HomepageHandbagsPresenter;
use App\Domain\Homepage\Support\HomepageHeroPresenter;
use App\Domain\Homepage\Support\HomepageHotSalePresenter;
use App\Domain\Homepage\Support\HomepageLimitedEditionPresenter;
use App\Domain\Homepage\Support\HomepageNewArrivalsPresenter;
use App\Domain\Homepage\Support\HomepageSummerEditPresenter;
use Illuminate\Contracts\View\View;

final class HomepageController
{
    public function __invoke(HomepageHeroPresenter $presenter, HomepageNewArrivalsPresenter $newArrivals, HomepageHotSalePresenter $hotSale, HomepageFutureStylePresenter $futureStyle, HomepageLimitedEditionPresenter $limitedEdition, HomepageExploreCollectionsPresenter $exploreCollections): View
    {
        return view('welcome', ['homepageClientStories' => app(HomepageClientStoriesPresenter::class)->present(), 'homepageHandbags' => app(HomepageHandbagsPresenter::class)->present(), 'homepageSummerEdit' => app(HomepageSummerEditPresenter::class)->present(), 'homepageDelivery' => app(HomepageDeliveryPresenter::class)->present(), 'homepageHero' => $presenter->present(), 'homepageNewArrivals' => $newArrivals->present(), 'homepageHotSale' => $hotSale->present(), 'homepageFutureStyle' => $futureStyle->present(), 'homepageLimitedEdition' => $limitedEdition->present(), 'homepageExploreCollections' => $exploreCollections->present()]);
    }
}
