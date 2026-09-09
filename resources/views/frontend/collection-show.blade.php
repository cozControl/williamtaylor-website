@extends('layouts.frontend')

@section('content')
<div id="root" class="min-h-screen bg-wt-offwhite">
    <header class="fixed top-0 left-0 right-0 z-40">
        @include('frontend.partials.announcement')
        @include('frontend.partials.header')
    </header>
    <main class="flex-1 pt-14 lg:pt-16 pb-16 lg:pb-0">
        <section data-collection-heading class="relative bg-wt-oxblood py-16 px-6 text-center overflow-hidden">
            <div aria-hidden="true" class="absolute inset-0 wt-pattern-texture wt-pattern-breathe pointer-events-none" style='background-image: url("/website/images/eab6bab5d_bg.jpg"); background-size: 300px;'></div>
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold/30 to-transparent"></div>
            <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold/30 to-transparent"></div>
            <div class="relative z-10">
                <img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0 mb-4" src="/website/images/cf030fe26_ICONlight.png" style="width: 22px; height: 22px;">
                <p class="font-label text-xs tracking-[0.3em] uppercase text-wt-gold mb-3">The Collection</p>
                <h1 class="font-heading text-5xl text-wt-cream">{{ $collection->currentDraftRevision->title }}</h1>
                @if ($collection->currentDraftRevision->short_description)
                    <p class="font-body text-sm text-wt-cream/60 font-light max-w-2xl mx-auto mt-3">{{ $collection->currentDraftRevision->short_description }}</p>
                @endif
            </div>
        </section>
        <section class="max-w-screen-xl mx-auto px-6 lg:px-12 py-10">
            <div data-collection-toolbar class="flex items-center justify-end mb-8 gap-4">
                <p class="font-label text-xs text-gray-400 uppercase tracking-wider">{{ $products->count() }} {{ \Illuminate\Support\Str::plural('Result', $products->count()) }}</p>
            </div>
            <div class="wt-collection-product-grid grid grid-cols-1 min-[480px]:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-10 lg:gap-x-6">
                @forelse ($products as $product)
                    <article data-collection-product-card>
                        @include('frontend.partials.product-card', ['card' => $product])
                    </article>
                @empty
                    <p class="font-body text-wt-charcoal/70">No products are currently available in this collection.</p>
                @endforelse
            </div>
        </section>
    </main>
    @include('frontend.partials.footer')
</div>
@endsection
