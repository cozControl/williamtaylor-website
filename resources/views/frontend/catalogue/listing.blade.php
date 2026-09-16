<div id="root"><div class="min-h-screen flex flex-col bg-wt-offwhite">
    @include('frontend.partials.header')
    <main data-catalogue-listing class="flex-1 pt-14 lg:pt-16 pb-16 lg:pb-0">
        <div class="min-h-screen bg-wt-offwhite pt-0">
            @include('frontend.catalogue.header')
            <div class="max-w-screen-xl mx-auto px-6 lg:px-12 py-10">
                @include('frontend.catalogue.toolbar')
                <div class="flex gap-8">
                    @include('frontend.catalogue.filters')
                    <div class="flex-1 min-w-0">
                        @include('frontend.catalogue.results')
                    </div>
                </div>
            </div>
        </div>
    </main>
    @include('frontend.partials.footer')
    @include('frontend.partials.mobile-bottom-navigation', ['activeBottomTab' => 'shop'])
    @include('frontend.partials.whatsapp-action')
</div></div>
@include('frontend.catalogue.styles')
<script src="{{ asset('website/js/catalogue-listing.js') }}" defer></script>
