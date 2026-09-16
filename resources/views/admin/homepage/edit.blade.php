@php
    $newArrivalsSelected = filled($hero->new_arrivals_collection_id);
    $newArrivalsNeedsAttention = $newArrivalsSelected
        && (! $newArrivals['managed'] || $newArrivals['attention_count'] > 0);

    $futureStyleSelected = collect([1, 2])
        ->filter(fn (int $position): bool => filled($hero->{"future_style_campaign_{$position}_id"}))
        ->count();
    $futureStyleAvailable = count($futureStyle['campaigns']);

    $limitedEditionSelected = collect([1, 2, 3])
        ->filter(fn (int $position): bool => filled($hero->{"limited_edition_campaign_{$position}_id"}))
        ->count();
    $limitedEditionAvailable = count($limitedEdition['campaigns']);
@endphp

<x-admin.layout title="Homepage" description="Manage the content and merchandising sections shown on your storefront." eyebrow="Website" :breadcrumbs="['Homepage' => null]">
    <x-slot:actions>
        <a class="admin-secondary-button" href="{{ route('home') }}" target="_blank" rel="noopener">View homepage</a>
    </x-slot:actions>

    <x-admin.flash :errors="$errors" />

    <div class="homepage-workspace" data-homepage-workspace>
        <header class="homepage-workspace-heading">
            <p>Homepage sections</p>
            <h2>Storefront sections</h2>
        </header>

        <x-admin.homepage-section-summary
            section-key="hero" position="1"
            title="Homepage Hero"
            :summary="$hero->eyebrow.'. '.$hero->title.'. '.($heroHasImage ? 'Background image configured.' : 'Using the storefront default background image.')"
            :status="$heroHasImage ? 'Configured' : 'Using storefront default'"
            :tone="$heroHasImage ? 'configured' : 'default'"
            :href="route('admin.homepage.hero.edit')"
            action-label="Manage Hero"
        />

        <x-admin.homepage-section-summary
            section-key="new-arrivals" position="2"
            title="New Arrivals"
            :summary="$newArrivalsNeedsAttention
                ? ($newArrivals['managed']
                    ? 'Source: '.$newArrivals['collection_name'].'. '.$newArrivals['ready_count'].' storefront-ready '.Str::plural('Product', $newArrivals['ready_count']).'; '.$newArrivals['attention_count'].' '.Str::plural('Product', $newArrivals['attention_count']).' needs attention.'
                    : 'The selected Collection is no longer available for the storefront.')
                : ($newArrivals['managed']
                    ? 'Source: '.$newArrivals['collection_name'].'. '.$newArrivals['ready_count'].' storefront-ready '.Str::plural('Product', $newArrivals['ready_count']).'.'
                    : 'Choose the Collection that supplies up to eight Product cards.')"
            :status="$newArrivalsNeedsAttention ? 'Needs attention' : ($newArrivals['managed'] ? 'Configured' : 'Using storefront default')"
            :tone="$newArrivalsNeedsAttention ? 'attention' : ($newArrivals['managed'] ? 'configured' : 'default')"
            :href="route('admin.homepage.new-arrivals.edit')"
            action-label="Manage New Arrivals"
        />

        <x-admin.homepage-section-summary
            section-key="hot-sale" position="3"
            title="William's Hot Sale"
            :summary="$hero->hot_sale_managed
                ? ($hotSale['managed'] ? 'Three editorial feature tiles are ready for the storefront.' : 'One or more editorial feature tiles needs attention.')
                : 'Manage the three editorial feature tiles shown in this section.'"
            :status="$hero->hot_sale_managed ? ($hotSale['managed'] ? 'Configured' : 'Needs attention') : 'Using storefront default'"
            :tone="$hero->hot_sale_managed ? ($hotSale['managed'] ? 'configured' : 'attention') : 'default'"
            :href="route('admin.homepage.hot-sale.edit')"
            action-label="Manage Hot Sale"
        />

        <x-admin.homepage-section-summary
            section-key="future-style" position="4"
            title="The Future of Style"
            :summary="$futureStyleSelected
                ? $futureStyleAvailable.' of '.$futureStyleSelected.' selected Pre-Order '.Str::plural('Campaign', $futureStyleSelected).' currently available.'
                : 'Showing eligible database Pre-Order Campaigns in schedule order.'"
            :status="$futureStyleSelected ? ($futureStyleAvailable < $futureStyleSelected ? 'Needs attention' : 'Configured') : 'Database campaigns'"
            :tone="$futureStyleSelected ? ($futureStyleAvailable < $futureStyleSelected ? 'attention' : 'configured') : 'default'"
            :href="route('admin.homepage.future-style.edit')"
            action-label="Manage The Future of Style"
        />

        <x-admin.homepage-section-summary
            section-key="limited-edition" position="5"
            title="Limited Edition"
            :summary="$limitedEditionSelected
                ? $limitedEditionAvailable.' of '.$limitedEditionSelected.' selected Limited Edition '.Str::plural('Campaign', $limitedEditionSelected).' currently available.'
                : 'Showing eligible database Limited Edition Campaigns in schedule order.'"
            :status="$limitedEditionSelected ? ($limitedEditionAvailable < $limitedEditionSelected ? 'Needs attention' : 'Configured') : 'Database campaigns'"
            :tone="$limitedEditionSelected ? ($limitedEditionAvailable < $limitedEditionSelected ? 'attention' : 'configured') : 'default'"
            :href="route('admin.homepage.limited-edition.edit')"
            action-label="Manage Limited Edition"
        />

        <x-admin.homepage-section-summary
            section-key="explore-collections" position="6"
            title="Explore the Collection"
            :summary="$hero->explore_collections_managed
                ? $exploreCollections['eligible_count'].' of '.$exploreCollections['configured_count'].' selected '.Str::plural('Collection', $exploreCollections['configured_count']).' currently available.'
                : 'Choose up to three Collection cards for customers to explore.'"
            :status="$hero->explore_collections_managed ? ($exploreCollections['attention_count'] > 0 ? 'Needs attention' : 'Configured') : 'Using storefront default'"
            :tone="$hero->explore_collections_managed ? ($exploreCollections['attention_count'] > 0 ? 'attention' : 'configured') : 'default'"
            :href="route('admin.homepage.explore-collections.edit')"
            action-label="Manage Explore the Collection"
        />
        @php
            $summerEdit = app(\App\Domain\Homepage\Support\HomepageSummerEditPresenter::class)->present();
        @endphp
        <x-admin.homepage-section-summary section-key="summer-edit" position="7" title="The Summer Edit" :summary="'Seasonal editorial feature'.($summerEdit['managed'] && $summerEdit['eligible'] ? ' · '.$summerEdit['destination']['title'] : '')" :status="$summerEdit['managed'] ? ($summerEdit['eligible'] ? 'Configured' : 'Needs attention') : 'Using storefront default'" :tone="$summerEdit['managed'] ? ($summerEdit['eligible'] ? 'configured' : 'attention') : 'default'" :href="route('admin.homepage.summer-edit.edit')" action-label="Manage The Summer Edit" />
        @php
            $delivery = app(\App\Domain\Homepage\Support\HomepageDeliveryPresenter::class)->present();
        @endphp
        <x-admin.homepage-section-summary section-key="delivery" position="8" title="Complimentary Delivery" summary="Delivery information · Shop with Confidence" :status="$delivery['managed'] ? ($delivery['eligible'] ? 'Configured' : 'Needs attention') : 'Using storefront default'" :tone="$delivery['managed'] ? ($delivery['eligible'] ? 'configured' : 'attention') : 'default'" :href="route('admin.homepage.delivery.edit')" action-label="Manage Complimentary Delivery" />
        @php
            $handbags = app(\App\Domain\Homepage\Support\HomepageHandbagsPresenter::class)->present();
        @endphp
        <x-admin.homepage-section-summary section-key="handbags" position="9" title="Women's Handbags" summary="Editorial hero and six curated Products" :status="$handbags['managed'] ? ($handbags['eligible'] && $handbags['ready_count'] > 0 ? 'Configured' : 'Needs attention') : 'Using storefront default'" :tone="$handbags['managed'] ? ($handbags['eligible'] && $handbags['ready_count'] > 0 ? 'configured' : 'attention') : 'default'" :href="route('admin.homepage.handbags.edit')" action-label="Manage Women's Handbags" />
        @php
            $clientStories = app(\App\Domain\Homepage\Support\HomepageClientStoriesPresenter::class)->present();
        @endphp
        <x-admin.homepage-section-summary section-key="client-stories" position="10" title="Client Stories" :summary="count($clientStories['stories']).' visible client stories ready for the storefront'" :status="$clientStories['managed'] ? ($clientStories['attention_count'] ? 'Needs attention' : (count($clientStories['stories']) ? 'Configured' : 'No visible stories')) : 'Using storefront default'" :tone="$clientStories['managed'] ? ($clientStories['attention_count'] ? 'attention' : 'configured') : 'default'" :href="route('admin.homepage.client-stories.edit')" action-label="Manage Client Stories" />

        <x-admin.homepage-section-summary section-key="follow-the-journey" position="11" title="Follow the Journey" summary="Show or hide the existing Instagram gallery. Links and media are preserved." status="Using storefront content" tone="default" />

    </div>
</x-admin.layout>
