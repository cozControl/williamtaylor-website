@extends('layouts.frontend')

@section('document-head')
 @include('frontend.partials.document-head')
 <style>
  [data-homepage-hero] { height: 100svh; }

  @media (max-width: 767px) {
   [data-homepage-hero] {
    box-sizing: border-box;
    min-height: 34rem;
    height: 100svh;
    padding-top: calc(4.5rem + env(safe-area-inset-top));
    padding-bottom: calc(6.5rem + env(safe-area-inset-bottom));
   }

   [data-homepage-hero-background] img {
    object-position: 58% top;
   }

   [data-homepage-hero-content] {
    width: 100%;
    max-width: 32rem;
    padding-inline: clamp(1.25rem, 6vw, 2rem);
   }

   [data-homepage-hero-content] > p:first-child {
    margin-bottom: 1rem;
    font-size: .625rem;
    line-height: 1.5;
    letter-spacing: .22em;
   }

   [data-homepage-hero-content] > h1 {
    font-size: clamp(2.75rem, 14vw, 4.5rem) !important;
    line-height: .95 !important;
    overflow-wrap: anywhere;
   }

   [data-homepage-hero-content] > h1 + p {
    margin-bottom: 1.75rem;
    font-size: clamp(.95rem, 4.5vw, 1.25rem) !important;
    line-height: 1.4;
   }

   [data-homepage-hero-actions] {
    display: grid;
    width: min(100%, 21rem);
    margin-inline: auto;
    gap: .75rem;
   }

   [data-homepage-hero-actions] > a {
    display: inline-flex;
    width: 100%;
    min-height: 3rem;
    align-items: center;
    justify-content: center;
    padding: .8rem 1.25rem;
   }

   [data-homepage-hero-scroll] {
    bottom: calc(5.25rem + env(safe-area-inset-bottom));
   }

   [data-homepage-hero-indicator] {
    right: 1rem;
    bottom: calc(5.25rem + env(safe-area-inset-bottom));
   }
  }

  @media (max-width: 767px) and (orientation: landscape) and (max-height: 540px) {
   [data-homepage-hero] {
    min-height: 30rem;
    padding-top: 4rem;
    padding-bottom: 4.5rem;
   }

   [data-homepage-hero-content] > h1 {
    font-size: clamp(2.5rem, 10vw, 3.75rem) !important;
   }

   [data-homepage-hero-scroll],
   [data-homepage-hero-indicator] {
    bottom: 1.25rem;
   }
  }
 </style>
@endsection

@section('content')
@php
    $homepageExploreCollectionsIsManaged = (bool) ($homepageExploreCollections['managed'] ?? false);
@endphp
<div id="root">
   <div class="min-h-screen flex flex-col bg-wt-offwhite">
    @include('frontend.partials.header')
    <main class="flex-1 pb-16 lg:pb-0">
     <div class="w-full">
      <section data-homepage-hero class="relative h-screen min-h-[600px] flex items-center justify-center overflow-hidden">
       <div data-homepage-hero-background class="absolute inset-0 transition-opacity duration-1500" style="opacity: 1;">
        <img alt="" class="w-full h-full object-cover object-top" src="{{ $homepageHero['background_url'] }}"/>
       </div>
       <div data-homepage-hero-content class="relative z-10 text-center px-6 max-w-5xl mx-auto" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'>
        <p class="text-xs tracking-[0.3em] uppercase text-wt-gold mb-6" style="opacity: 1; transform: none;">
         {{ $homepageHero['eyebrow'] }}
        </p>
        <h1 class="text-wt-cream mb-4" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-size: clamp(3rem, 10vw, 7rem); line-height: 1; letter-spacing: -0.02em; font-weight: 300; opacity: 1; transform: none;'>
         {{ $homepageHero['title'] }}
        </h1>
        <p class="text-wt-cream mb-10" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-size: clamp(1rem, 3vw, 1.75rem); font-style: italic; font-weight: 300; opacity: 1; transform: none;'>
         {{ $homepageHero['subtitle'] }}
        </p>
        <div data-homepage-hero-actions class="flex flex-col sm:flex-row gap-4 justify-center" style="opacity: 1; transform: none;">
         <a class="btn-gold px-12 py-4 text-sm inline-flex items-center gap-2" href="{{ $homepageHero['primary_cta_url'] }}" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 400;'>
          <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 20px; height: 20px;">
           <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 14px; height: 14px;"/>
          </span>
          {{ $homepageHero['primary_cta_label'] }}
         </a>
         <a class="border border-wt-cream text-wt-cream hover:bg-wt-cream hover:text-wt-oxblood transition-all duration-300 px-12 py-4 text-xs tracking-widest uppercase" href="{{ $homepageHero['secondary_cta_url'] }}" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 400;'>
          {{ $homepageHero['secondary_cta_label'] }}
         </a>
        </div>
       </div>
       <div data-homepage-hero-scroll class="absolute bottom-24 lg:bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-wt-cream/60{{ $homepageHero['scroll_indicator_enabled'] ? '' : ' hidden' }}" style="opacity: 1;">
        <span class="font-label text-[10px] tracking-[0.2em] uppercase">
         Scroll
        </span>
        <div style="transform: translateY(2.89675px);">
         <svg class="lucide lucide-chevron-down" fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
          <path d="m6 9 6 6 6-6">
          </path>
         </svg>
        </div>
       </div>
       <div data-homepage-hero-indicator class="absolute bottom-24 lg:bottom-8 right-4 lg:right-8 flex gap-2">
        <button class="w-1 h-6 transition-all duration-300 bg-wt-gold">
        </button>
       </div>
      </section>
      <section class="bg-white border-y border-gray-100 py-6 lg:py-10">
       <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
        <div class="flex justify-center mb-6 hidden">
         <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 26px; height: 26px;">
          <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"/>
         </span>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-8">
         <div class="flex flex-col items-center text-center gap-3" style="opacity: 1; transform: none;">
          <svg class="lucide lucide-gem text-wt-gold" fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewbox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
           <path d="M6 3h12l4 6-10 13L2 9Z">
           </path>
           <path d="M11 3 8 9l4 13 4-13-3-6">
           </path>
           <path d="M2 9h20">
           </path>
          </svg>
          <div>
           <p class="font-label text-xs tracking-widest uppercase text-wt-oxblood font-semibold">
            Premium Fabrics
           </p>
           <p class="font-body text-xs text-gray-500 font-light mt-0.5">
            Italian &amp; Belgian sourced
           </p>
          </div>
         </div>
         <div class="flex flex-col items-center text-center gap-3" style="opacity: 1; transform: none;">
          <svg class="lucide lucide-scissors text-wt-gold" fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewbox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
           <circle cx="6" cy="6" r="3">
           </circle>
           <path d="M8.12 8.12 12 12">
           </path>
           <path d="M20 4 8.12 15.88">
           </path>
           <circle cx="6" cy="18" r="3">
           </circle>
           <path d="M14.8 14.8 20 20">
           </path>
          </svg>
          <div>
           <p class="font-label text-xs tracking-widest uppercase text-wt-oxblood font-semibold">
            Handcrafted Details
           </p>
           <p class="font-body text-xs text-gray-500 font-light mt-0.5">
            Finished by hand in Dar es Salaam
           </p>
          </div>
         </div>
         <div class="flex flex-col items-center text-center gap-3" style="opacity: 1; transform: none;">
          <svg class="lucide lucide-truck text-wt-gold" fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewbox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
           <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2">
           </path>
           <path d="M15 18H9">
           </path>
           <path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14">
           </path>
           <circle cx="17" cy="18" r="2">
           </circle>
           <circle cx="7" cy="18" r="2">
           </circle>
          </svg>
          <div>
           <p class="font-label text-xs tracking-widest uppercase text-wt-oxblood font-semibold">
            Express Delivery
           </p>
           <p class="font-body text-xs text-gray-500 font-light mt-0.5">
            Same-day in Dar es Salaam
           </p>
          </div>
         </div>
         <div class="flex flex-col items-center text-center gap-3" style="opacity: 1; transform: none;">
          <svg class="lucide lucide-rotate-ccw text-wt-gold" fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" viewbox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
           <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8">
           </path>
           <path d="M3 3v5h5">
           </path>
          </svg>
          <div>
           <p class="font-label text-xs tracking-widest uppercase text-wt-oxblood font-semibold">
            Easy Returns
           </p>
           <p class="font-body text-xs text-gray-500 font-light mt-0.5">
            14-day free returns
           </p>
          </div>
         </div>
        </div>
       </div>
      </section>
      @if($homepageNewArrivals['managed'])
       @include('frontend.partials.homepage-new-arrivals')
      @else
      <section class="py-12 lg:py-20 bg-wt-offwhite">
       <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
        <div class="flex items-end justify-between mb-8 lg:mb-12">
         <div>
          <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-2" style="width: 26px; height: 26px;">
           <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"/>
          </span>
          <p class="section-subtitle mb-2">
           Just Arrived
          </p>
          <h2 class="section-title">
           New Arrivals
          </h2>
         </div>
         <div class="flex items-center gap-4">
          <div class="hidden md:flex gap-2">
           <button class="w-10 h-10 border border-wt-oxblood flex items-center justify-center hover:bg-wt-oxblood hover:text-wt-cream transition-all">
            <svg class="lucide lucide-chevron-left" fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
             <path d="m15 18-6-6 6-6">
             </path>
            </svg>
           </button>
           <button class="w-10 h-10 border border-wt-oxblood flex items-center justify-center hover:bg-wt-oxblood hover:text-wt-cream transition-all">
            <svg class="lucide lucide-chevron-right" fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
             <path d="m9 18 6-6-6-6">
             </path>
            </svg>
           </button>
          </div>
          <a class="btn-outline text-xs py-2 px-6 hidden sm:block" href="{{ route('products.index', ['sort' => 'newest']) }}">
           View All
          </a>
         </div>
        </div>
        <div class="flex gap-4 overflow-x-auto scrollbar-hide pb-4 md:overflow-visible md:grid md:grid-cols-4 md:gap-6" style="scroll-snap-type: x mandatory;">
         <div class="flex-shrink-0 w-60 md:w-auto" style="scroll-snap-align: start;">
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="{{ route('products.taylor-oxford-shirt') }}">
             <img alt="The Taylor Oxford Shirt" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/0368482bc_image.jpg"/>
            </a>
            <div class="absolute top-3 left-3 flex flex-col gap-1">
             <span class="bg-wt-gold text-wt-oxblood font-label px-2 py-0.5 text-[10px] tracking-widest">
              NEW
             </span>
             <span class="bg-wt-oxblood text-wt-cream font-label px-2 py-0.5 text-[10px] tracking-widest">
              BESTSELLER
             </span>
            </div>
            <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
             <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
              </path>
             </svg>
            </button>
           </div>
           <div class="mt-3">
            <div class="flex gap-1 mb-2">
             <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(245, 240, 232);" title="Ivory">
             </div>
             <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(26, 26, 26);" title="Noir">
             </div>
            </div>
            <a href="{{ route('products.taylor-oxford-shirt') }}">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              The Taylor Oxford Shirt
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 285,000
              </span>
             </span>
            </p>
           </div>
          </div>
         </div>
         <div class="flex-shrink-0 w-60 md:w-auto" style="scroll-snap-align: start;">
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="{{ route('products.mercerized-cotton-polo') }}">
             <img alt="Mercerized Cotton Polo" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/db23eed31_image.jpg"/>
            </a>
            <div class="absolute top-3 left-3 flex flex-col gap-1">
            </div>
            <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
             <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
              </path>
             </svg>
            </button>
           </div>
           <div class="mt-3">
            <div class="flex gap-1 mb-2">
             <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(196, 149, 106);" title="Camel">
             </div>
             <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(212, 184, 150);" title="Sand">
             </div>
            </div>
            <a href="{{ route('products.mercerized-cotton-polo') }}">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              Mercerized Cotton Polo
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 195,000
              </span>
             </span>
            </p>
           </div>
          </div>
         </div>
         <div class="flex-shrink-0 w-60 md:w-auto" style="scroll-snap-align: start;">
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="{{ route('products.dar-es-salaam-linen-suit') }}">
             <img alt="The Dar es Salaam Linen Suit" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/da608a583_image.jpg"/>
            </a>
            <div class="absolute top-3 left-3 flex flex-col gap-1">
             <span class="bg-wt-gold text-wt-oxblood font-label px-2 py-0.5 text-[10px] tracking-widest">
              LIMITED
             </span>
            </div>
            <div class="absolute bottom-3 left-3">
             <span class="bg-wt-gold text-wt-oxblood font-label text-[10px] tracking-widest px-2 py-0.5">
              Only 30 Made
             </span>
            </div>
            <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
             <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
              </path>
             </svg>
            </button>
           </div>
           <div class="mt-3">
            <a href="{{ route('products.dar-es-salaam-linen-suit') }}">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              The Dar es Salaam Linen Suit
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 1,250,000
              </span>
             </span>
            </p>
           </div>
          </div>
         </div>
         <div class="flex-shrink-0 w-60 md:w-auto" style="scroll-snap-align: start;">
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="{{ route('products.slim-tapered-chinos') }}">
             <img alt="Slim Tapered Chinos" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/8d7122778_image.jpg"/>
            </a>
            <div class="absolute top-3 left-3 flex flex-col gap-1">
            </div>
            <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
             <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
              </path>
             </svg>
            </button>
           </div>
           <div class="mt-3">
            <div class="flex gap-1 mb-2">
             <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(143, 169, 138);" title="Sage">
             </div>
             <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(245, 237, 214);" title="Cream">
             </div>
            </div>
            <a href="{{ route('products.slim-tapered-chinos') }}">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              Slim Tapered Chinos
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 245,000
              </span>
             </span>
            </p>
           </div>
          </div>
         </div>
         <div class="flex-shrink-0 w-60 md:w-auto" style="scroll-snap-align: start;">
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="{{ route('products.executive-overcoat') }}">
             <img alt="The Executive Overcoat" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/81f56a965_image.jpg"/>
            </a>
            <div class="absolute top-3 left-3 flex flex-col gap-1">
             <span class="bg-wt-oxblood text-wt-cream font-label px-2 py-0.5 text-[10px] tracking-widest">
              PRE-ORDER
             </span>
            </div>
            <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
             <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
              </path>
             </svg>
            </button>
           </div>
           <div class="mt-3">
            <a href="{{ route('products.executive-overcoat') }}">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              The Executive Overcoat
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 890,000
              </span>
             </span>
            </p>
            <p class="font-label text-xs tracking-wider text-wt-gold uppercase mt-0.5">
             Pre-Order · Ships 2026-08-15
            </p>
           </div>
          </div>
         </div>
         <div class="flex-shrink-0 w-60 md:w-auto" style="scroll-snap-align: start;">
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="html/zanzibar-resort-shirt.html">
             <img alt="The Zanzibar Resort Shirt" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/ee86da3ae_image.jpg"/>
            </a>
            <div class="absolute top-3 left-3 flex flex-col gap-1">
             <span class="bg-wt-gold text-wt-oxblood font-label px-2 py-0.5 text-[10px] tracking-widest">
              NEW
             </span>
            </div>
            <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
             <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
              </path>
             </svg>
            </button>
           </div>
           <div class="mt-3">
            <div class="flex gap-1 mb-2">
             <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(232, 224, 208);" title="Natural Stripe">
             </div>
             <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(245, 240, 232);" title="Ivory Crochet">
             </div>
            </div>
            <a href="html/zanzibar-resort-shirt.html">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              The Zanzibar Resort Shirt
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 265,000
              </span>
             </span>
            </p>
           </div>
          </div>
         </div>
         <div class="flex-shrink-0 w-60 md:w-auto" style="scroll-snap-align: start;">
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="html/unisex-oversized-tee.html">
             <img alt="Unisex Oversized Tee" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/b079734c1_image.jpg"/>
            </a>
            <div class="absolute top-3 left-3 flex flex-col gap-1">
            </div>
            <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
             <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
              </path>
             </svg>
            </button>
           </div>
           <div class="mt-3">
            <a href="html/unisex-oversized-tee.html">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              Unisex Oversized Tee
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 145,000
              </span>
             </span>
            </p>
           </div>
          </div>
         </div>
         <div class="flex-shrink-0 w-60 md:w-auto" style="scroll-snap-align: start;">
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="html/relaxed-fit-denim-jacket.html">
             <img alt="Relaxed Fit Denim Jacket" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/84c5dcdc1_image.jpg"/>
            </a>
            <div class="absolute top-3 left-3 flex flex-col gap-1">
             <span class="bg-wt-oxblood text-wt-cream font-label px-2 py-0.5 text-[10px] tracking-widest">
              BESTSELLER
             </span>
            </div>
            <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
             <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
              </path>
             </svg>
            </button>
           </div>
           <div class="mt-3">
            <a href="html/relaxed-fit-denim-jacket.html">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              Relaxed Fit Denim Jacket
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 380,000
              </span>
             </span>
            </p>
           </div>
          </div>
         </div>
        </div>
       </div>
      </section>
      @endif
       @if($homepageHotSale['managed'])
       @include('frontend.partials.homepage-hot-sale')
       @else
       <section class="py-12 lg:py-20 bg-white">
       <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
        <div class="text-center mb-10 lg:mb-14">
         <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-3" style="width: 29px; height: 29px;">
          <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 20px; height: 20px;"/>
         </span>
         <p class="section-subtitle mb-2 text-wt-gold">
          Limited Time
         </p>
         <h2 class="section-title text-wt-oxblood" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'>
          William's Hot Sale
         </h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
         <div class="group relative overflow-hidden aspect-[3/4] cursor-pointer" style="opacity: 1; transform: none;">
          <a href="{{ route('products.index', ['sort' => 'newest']) }}">
           <img alt="The Atelier Edit" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-108" src="/website/images/86df796c0_thumb.jpg"/>
           <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/80 via-wt-oxblood/20 to-transparent">
           </div>
           <div class="absolute top-3 left-3 w-7 h-7 rounded-full bg-wt-gold/90 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
            <span class="font-label text-[9px] font-bold text-wt-oxblood">
             01
            </span>
           </div>
           <div class="absolute inset-0 flex flex-col items-center justify-end p-4 lg:p-6 text-center">
            <div class="group-hover:-translate-y-2 transition-transform duration-300">
             <h3 class="font-heading text-xl lg:text-2xl text-wt-cream mb-1.5">
              The Atelier Edit
             </h3>
             <p class="font-body text-xs lg:text-sm text-wt-cream/70 font-light mb-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Statement pieces from the house, hand-finished in Dar es Salaam.
             </p>
             <span class="font-label text-xs tracking-widest uppercase text-wt-gold border-b border-wt-gold pb-0.5">
              Discover →
             </span>
            </div>
           </div>
          </a>
         </div>
         <div class="group relative overflow-hidden aspect-[3/4] cursor-pointer" style="opacity: 1; transform: none;">
          <a href="{{ route('collections.index') }}">
           <video autoplay="" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-108" loop="" playsinline="" src="https://media.base44.com/videos/public/6a4d9ad469285a7e6df866f1/2eaa51040_3333.mp4">
           </video>
           <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/80 via-wt-oxblood/20 to-transparent">
           </div>
           <div class="absolute top-3 left-3 w-7 h-7 rounded-full bg-wt-gold/90 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
            <span class="font-label text-[9px] font-bold text-wt-oxblood">
             02
            </span>
           </div>
           <div class="absolute inset-0 flex flex-col items-center justify-end p-4 lg:p-6 text-center">
            <div class="group-hover:-translate-y-2 transition-transform duration-300">
             <h3 class="font-heading text-xl lg:text-2xl text-wt-cream mb-1.5">
              The Shopping Experience
             </h3>
             <p class="font-body text-xs lg:text-sm text-wt-cream/70 font-light mb-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Carry the collection home in signature William Taylor style.
             </p>
             <span class="font-label text-xs tracking-widest uppercase text-wt-gold border-b border-wt-gold pb-0.5">
              Discover →
             </span>
            </div>
           </div>
          </a>
         </div>
         <div class="group relative overflow-hidden aspect-[3/4] cursor-pointer" style="opacity: 1; transform: none;">
          <a href="{{ route('products.index') }}">
           <img alt="The Signature Bag" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-108" src="/website/images/8572d276e_thumb.jpg"/>
           <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/80 via-wt-oxblood/20 to-transparent">
           </div>
           <div class="absolute top-3 left-3 w-7 h-7 rounded-full bg-wt-gold/90 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
            <span class="font-label text-[9px] font-bold text-wt-oxblood">
             03
            </span>
           </div>
           <div class="absolute inset-0 flex flex-col items-center justify-end p-4 lg:p-6 text-center">
            <div class="group-hover:-translate-y-2 transition-transform duration-300">
             <h3 class="font-heading text-xl lg:text-2xl text-wt-cream mb-1.5">
              The Signature Bag
             </h3>
             <p class="font-body text-xs lg:text-sm text-wt-cream/70 font-light mb-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Oxblood and gold — the William Taylor hallmark, carried worldwide.
             </p>
             <span class="font-label text-xs tracking-widest uppercase text-wt-gold border-b border-wt-gold pb-0.5">
              Discover →
             </span>
            </div>
           </div>
          </a>
         </div>
        </div>
       </div>
       </section>
       @endif
       @include('frontend.partials.homepage-future-style')
      @include('frontend.partials.homepage-limited-edition')
      @if($homepageExploreCollectionsIsManaged)
       @include('frontend.partials.homepage-explore-collections')
      @else
      <section class="py-12 lg:py-20 bg-white">
       <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
        <div class="text-center mb-10 lg:mb-14">
         <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-3" style="width: 29px; height: 29px;">
          <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 20px; height: 20px;"/>
         </span>
         <p class="section-subtitle mb-2 text-wt-gold">
          Shop By Category
         </p>
         <h2 class="section-title text-wt-oxblood" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'>
          Explore the Collection
         </h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
         <div class="group relative overflow-hidden aspect-[3/4] cursor-pointer" style="opacity: 1; transform: none;">
          <a href="html/mens-wear.html">
           <img alt="Men's Wear" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-108" src="/website/images/1c4c991a1_ethumb.jpg" style="--tw-scale-x: 1.08; --tw-scale-y: 1.08;"/>
           <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/80 via-wt-oxblood/20 to-transparent">
           </div>
           <div class="absolute inset-0 flex flex-col items-center justify-end p-6 lg:p-8 text-center">
            <div class="group-hover:-translate-y-2 transition-transform duration-300" style="transform: translateY(10px);">
             <h3 class="font-heading text-2xl text-wt-cream mb-2">
              Men's Wear
             </h3>
             <p class="font-body text-sm text-wt-cream/70 font-light mb-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              The definitive wardrobe for the modern man
             </p>
             <span class="font-label text-xs tracking-widest uppercase text-wt-gold border-b border-wt-gold pb-0.5">
              Explore →
             </span>
            </div>
           </div>
          </a>
         </div>
         <div class="group relative overflow-hidden aspect-[3/4] cursor-pointer" style="opacity: 1; transform: none;">
          <a href="html/unisex.html">
           <img alt="Unisex" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-108" src="/website/images/a5552b423_3.jpg" style="--tw-scale-x: 1.08; --tw-scale-y: 1.08;"/>
           <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/80 via-wt-oxblood/20 to-transparent">
           </div>
           <div class="absolute inset-0 flex flex-col items-center justify-end p-6 lg:p-8 text-center">
            <div class="group-hover:-translate-y-2 transition-transform duration-300" style="transform: translateY(10px);">
             <h3 class="font-heading text-2xl text-wt-cream mb-2">
              Unisex
             </h3>
             <p class="font-body text-sm text-wt-cream/70 font-light mb-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              Gender-free dressing, crafted for all
             </p>
             <span class="font-label text-xs tracking-widest uppercase text-wt-gold border-b border-wt-gold pb-0.5">
              Explore →
             </span>
            </div>
           </div>
          </a>
         </div>
         <div class="group relative overflow-hidden aspect-[3/4] cursor-pointer" style="opacity: 1; transform: none;">
          <a href="html/accessories.html">
           <video autoplay="" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-108" loop="" playsinline="" src="https://media.base44.com/videos/public/6a4d9ad469285a7e6df866f1/eb1f94bda_SnapInsta-Ai_3923038057139474583_77059721635.mp4">
           </video>
           <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/80 via-wt-oxblood/20 to-transparent">
           </div>
           <div class="absolute inset-0 flex flex-col items-center justify-end p-6 lg:p-8 text-center">
            <div class="group-hover:-translate-y-2 transition-transform duration-300" style="transform: translateY(10px);">
             <h3 class="font-heading text-2xl text-wt-cream mb-2">
              Accessories
             </h3>
             <p class="font-body text-sm text-wt-cream/70 font-light mb-4 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
              The details that define the gentleman
             </p>
             <span class="font-label text-xs tracking-widest uppercase text-wt-gold border-b border-wt-gold pb-0.5">
              Explore →
             </span>
            </div>
           </div>
          </a>
         </div>
        </div>
       </div>
      </section>
      @endif
      <section class="relative bg-wt-oxblood overflow-hidden">
       <div class="wt-pattern-texture wt-pattern-breathe-band wt-pattern-radial-mask absolute inset-0 z-0" style='background-image: url("/website/images/cf030fe26_ICONlight.png"); background-size: 280px;'>
       </div>
@if($homepageSummerEdit['managed'])
@include('frontend.partials.homepage-summer-edit')
@else
       <div class="relative z-10 px-4 lg:px-12 py-8 lg:py-10">
        <div class="max-w-screen-xl mx-auto">
         <div class="relative overflow-hidden border border-wt-gold/40">
          <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold to-transparent z-20">
          </div>
          <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold to-transparent z-20">
          </div>
          <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[120%] h-[180%] bg-[radial-gradient(circle,rgba(201,169,98,0.15),transparent_50%)] z-0">
          </div>
          <div class="relative z-10 flex flex-col items-center text-center px-6 py-10 sm:py-12 lg:py-14">
           <div style="opacity: 1; transform: none;">
            <div class="flex items-center justify-center gap-3 mb-4">
             <div class="h-px w-10 bg-wt-gold/40">
             </div>
             <img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"/>
             <div class="h-px w-10 bg-wt-gold/40">
             </div>
            </div>
            <p class="font-label text-[10px] sm:text-xs tracking-[0.35em] uppercase text-wt-gold mb-4">
             Summer 2026
            </p>
            <h2 class="font-heading text-wt-cream mb-4" style="font-size: clamp(1.75rem, 4vw, 2.75rem); line-height: 1.1; text-shadow: rgba(0, 0, 0, 0.5) 0px 4px 30px;">
             The Summer Edit
            </h2>
            <p class="font-body text-sm text-wt-cream/70 font-light max-w-md mx-auto mb-6">
             Up to
             <span class="text-wt-gold font-medium">
              30% Off
             </span>
             selected styles. An invitation to acquire curated pieces at exceptional value.
            </p>
            <a class="btn-gold px-10 py-3.5 text-xs" href="{{ route('products.index', ['filter' => 'sale']) }}">
             Shop the Edit
            </a>
           </div>
          </div>
         </div>
        </div>
       </div>
@endif
@if($homepageDelivery['managed'])
@include('frontend.partials.homepage-delivery')
@else
       <div class="relative z-10 bg-wt-cream border-y border-wt-gold/40 py-5 px-6">
        <div class="wt-pattern-texture absolute inset-0 opacity-[0.04]" style='background-image: url("/website/images/cf030fe26_ICONlight.png"); background-size: 240px;'>
        </div>
        <div class="relative max-w-screen-xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3">
         <div class="flex items-center gap-3">
          <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 26px; height: 26px;">
           <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"/>
          </span>
          <div>
           <p class="font-label text-[10px] tracking-[0.35em] uppercase text-wt-gold mb-0.5">
            Dar es Salaam
           </p>
           <h3 class="font-heading text-lg sm:text-xl text-wt-oxblood">
            Complimentary Delivery in Dar es Salaam
           </h3>
          </div>
         </div>
         <a class="btn-primary text-xs py-2.5 px-7 flex-shrink-0" href="html/contact.html">
          Shop with Confidence
         </a>
        </div>
       </div>
@endif
      </section>
 @if($homepageHandbags['managed'])
@include('frontend.partials.homepage-handbags')
@else
     <section class="py-12 lg:py-20 bg-wt-offwhite">
       <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
        <div class="flex items-end justify-between mb-8 lg:mb-12">
         <div>
          <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-2" style="width: 26px; height: 26px;">
           <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 18px; height: 18px;"/>
          </span>
          <p class="section-subtitle mb-2">
           For Her
          </p>
          <h2 class="section-title">
           Women's Handbags
          </h2>
         </div>
         <a class="btn-outline text-xs py-2 px-6 hidden sm:block" href="html/womens-handbags.html">
          View All
         </a>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
         <div class="lg:col-span-4">
          <div class="relative h-full min-h-[400px] lg:min-h-full overflow-hidden group" style="opacity: 0; transform: translateX(-30px);">
           <img alt="Women's Handbag Collection" class="absolute inset-0 w-full h-full object-cover object-center" src="/website/images/a3ab973a9_waa2.png" style="opacity: 1; transform: none;"/>
           <div class="absolute inset-0 bg-gradient-to-t from-wt-oxblood/90 via-wt-oxblood/30 to-transparent">
           </div>
           <div class="absolute bottom-4 right-4 flex gap-2 z-10">
            <button aria-label="Slide 1" class="w-2 h-2 rounded-full transition-all bg-wt-gold w-6">
            </button>
            <button aria-label="Slide 2" class="w-2 h-2 rounded-full transition-all bg-wt-cream/50">
            </button>
           </div>
           <div class="relative h-full flex flex-col justify-end p-6 lg:p-8">
            <img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0 mb-3" src="/website/images/cf030fe26_ICONlight.png" style="width: 20px; height: 20px;"/>
            <p class="section-subtitle mb-2">
             New Collection
            </p>
            <h3 class="font-heading text-2xl lg:text-3xl text-wt-cream mb-3" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'>
             Crafted for Her
            </h3>
            <p class="font-body text-sm text-wt-cream/80 font-light mb-5 max-w-xs">
             From totes to clutches — each piece handcrafted in our Dar es Salaam atelier.
            </p>
            <a class="btn-gold text-xs py-3 px-6 self-start" href="html/womens-handbags.html">
             Shop the Collection
            </a>
           </div>
          </div>
         </div>
         <div class="lg:col-span-8">
          <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
           <div style="opacity: 0; transform: translateY(20px);">
            <div class="group relative" style="opacity: 0; transform: translateY(20px);">
             <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
              <a href="html/savanna-tote-bag.html">
               <img alt="The Savanna Tote Bag" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/fec1e30ee_generated_image.png"/>
              </a>
              <div class="absolute top-3 left-3 flex flex-col gap-1">
               <span class="bg-wt-gold text-wt-oxblood font-label px-2 py-0.5 text-[10px] tracking-widest">
                NEW
               </span>
              </div>
              <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
               <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
                </path>
               </svg>
              </button>
             </div>
             <div class="mt-3">
              <a href="html/savanna-tote-bag.html">
               <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
                The Savanna Tote Bag
               </h3>
              </a>
              <p class="font-label text-sm text-wt-oxblood mt-1">
               <span class="inline-flex items-center gap-1.5">
                <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
                 <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
                </span>
                <span>
                 TZS 420,000
                </span>
               </span>
              </p>
             </div>
            </div>
           </div>
           <div style="opacity: 0; transform: translateY(20px);">
            <div class="group relative" style="opacity: 0; transform: translateY(20px);">
             <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
              <a href="html/serengeti-shoulder-bag.html">
               <img alt="The Serengeti Shoulder Bag" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/5e4275a15_generated_image.png"/>
              </a>
              <div class="absolute top-3 left-3 flex flex-col gap-1">
               <span class="bg-wt-oxblood text-wt-cream font-label px-2 py-0.5 text-[10px] tracking-widest">
                BESTSELLER
               </span>
              </div>
              <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
               <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
                </path>
               </svg>
              </button>
             </div>
             <div class="mt-3">
              <a href="html/serengeti-shoulder-bag.html">
               <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
                The Serengeti Shoulder Bag
               </h3>
              </a>
              <p class="font-label text-sm text-wt-oxblood mt-1">
               <span class="inline-flex items-center gap-1.5">
                <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
                 <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
                </span>
                <span>
                 TZS 350,000
                </span>
               </span>
              </p>
             </div>
            </div>
           </div>
           <div style="opacity: 0; transform: translateY(20px);">
            <div class="group relative" style="opacity: 0; transform: translateY(20px);">
             <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
              <a href="html/kilimanjaro-clutch.html">
               <img alt="The Kilimanjaro Clutch" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/1aaa8bb8c_generated_image.png"/>
              </a>
              <div class="absolute top-3 left-3 flex flex-col gap-1">
               <span class="bg-wt-gold text-wt-oxblood font-label px-2 py-0.5 text-[10px] tracking-widest">
                NEW
               </span>
              </div>
              <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
               <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
                </path>
               </svg>
              </button>
             </div>
             <div class="mt-3">
              <a href="html/kilimanjaro-clutch.html">
               <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
                The Kilimanjaro Clutch
               </h3>
              </a>
              <p class="font-label text-sm text-wt-oxblood mt-1">
               <span class="inline-flex items-center gap-1.5">
                <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
                 <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
                </span>
                <span>
                 TZS 185,000
                </span>
               </span>
              </p>
             </div>
            </div>
           </div>
           <div style="opacity: 0; transform: translateY(20px);">
            <div class="group relative" style="opacity: 0; transform: translateY(20px);">
             <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
              <a href="html/zanzibar-crossbody.html">
               <img alt="The Zanzibar Crossbody" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/21856d944_generated_image.png"/>
              </a>
              <div class="absolute top-3 left-3 flex flex-col gap-1">
              </div>
              <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
               <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
                </path>
               </svg>
              </button>
             </div>
             <div class="mt-3">
              <a href="html/zanzibar-crossbody.html">
               <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
                The Zanzibar Crossbody
               </h3>
              </a>
              <p class="font-label text-sm text-wt-oxblood mt-1">
               <span class="inline-flex items-center gap-1.5">
                <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
                 <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
                </span>
                <span>
                 TZS 295,000
                </span>
               </span>
              </p>
             </div>
            </div>
           </div>
           <div style="opacity: 0; transform: translateY(20px);">
            <div class="group relative" style="opacity: 0; transform: translateY(20px);">
             <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
              <a href="html/dar-bag-mini.html">
               <img alt="The Dar Bag Mini" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/62414b4d4_generated_image.png"/>
              </a>
              <div class="absolute top-3 left-3 flex flex-col gap-1">
               <span class="bg-wt-gold text-wt-oxblood font-label px-2 py-0.5 text-[10px] tracking-widest">
                LIMITED
               </span>
              </div>
              <div class="absolute bottom-3 left-3">
               <span class="bg-wt-gold text-wt-oxblood font-label text-[10px] tracking-widest px-2 py-0.5">
                Only 35 Made
               </span>
              </div>
              <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
               <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
                </path>
               </svg>
              </button>
             </div>
             <div class="mt-3">
              <a href="html/dar-bag-mini.html">
               <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
                The Dar Bag Mini
               </h3>
              </a>
              <p class="font-label text-sm text-wt-oxblood mt-1">
               <span class="inline-flex items-center gap-1.5">
                <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
                 <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
                </span>
                <span>
                 TZS 250,000
                </span>
               </span>
              </p>
             </div>
            </div>
           </div>
           <div style="opacity: 0; transform: translateY(20px);">
            <div class="group relative" style="opacity: 0; transform: translateY(20px);">
             <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
              <a href="html/arusha-bucket-bag.html">
               <img alt="The Arusha Bucket Bag" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/0f5271d44_generated_image.png"/>
              </a>
              <div class="absolute top-3 left-3 flex flex-col gap-1">
               <span class="bg-wt-oxblood text-wt-cream font-label px-2 py-0.5 text-[10px] tracking-widest">
                BESTSELLER
               </span>
              </div>
              <button aria-label="Add to wishlist" class="absolute top-3 right-3 w-8 h-8 bg-white/90 flex items-center justify-center shadow transition-all hover:bg-wt-oxblood group/heart">
               <svg class="lucide lucide-heart transition-colors text-gray-500 group-hover/heart:text-wt-cream" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
                </path>
               </svg>
              </button>
             </div>
             <div class="mt-3">
              <a href="html/arusha-bucket-bag.html">
               <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
                The Arusha Bucket Bag
               </h3>
              </a>
              <p class="font-label text-sm text-wt-oxblood mt-1">
               <span class="inline-flex items-center gap-1.5">
                <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
                 <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
                </span>
                <span>
                 TZS 310,000
                </span>
               </span>
              </p>
             </div>
            </div>
           </div>
          </div>
         </div>
        </div>
       </div>
      </section>
 @endif
@if($homepageClientStories['managed'])
@include('frontend.partials.homepage-client-stories')
@else
     <section class="py-12 lg:py-20 bg-wt-cream">
       <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
        <div class="text-center mb-10 lg:mb-14">
         <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0 mb-3" style="width: 29px; height: 29px;">
          <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 20px; height: 20px;"/>
         </span>
         <p class="section-subtitle mb-2 text-wt-gold">
          Client Stories
         </p>
         <h2 class="section-title text-wt-oxblood" style='font-family: Avenir, "Avenir Next", "Helvetica Neue", sans-serif; font-weight: 300;'>
          What They Say
         </h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
         <div class="bg-white p-6 lg:p-8 relative" style="opacity: 0; transform: translateY(30px);">
          <span class="font-heading text-6xl text-wt-gold leading-none absolute top-4 left-6 opacity-30">
           "
          </span>
          <p class="font-body text-sm text-gray-600 font-light leading-relaxed mb-6 relative z-10 pt-4">
           William Taylor has redefined what it means to dress well in East Africa. The Dar es Salaam Linen Suit is the most complimented piece I've ever owned.
          </p>
          <div class="flex items-center gap-3">
           <div class="w-10 h-10 rounded-full overflow-hidden border border-wt-gold/30">
            <img alt="James M." class="w-full h-full object-cover object-top" src="/website/images/b9c0b1bdf_image.jpg"/>
           </div>
           <div>
            <p class="font-label text-xs font-semibold tracking-wider uppercase text-wt-oxblood">
             James M.
            </p>
            <p class="font-body text-xs text-gray-400 font-light">
             Dar es Salaam, Tanzania
            </p>
           </div>
          </div>
         </div>
         <div class="bg-white p-6 lg:p-8 relative" style="opacity: 0; transform: translateY(30px);">
          <span class="font-heading text-6xl text-wt-gold leading-none absolute top-4 left-6 opacity-30">
           "
          </span>
          <p class="font-body text-sm text-gray-600 font-light leading-relaxed mb-6 relative z-10 pt-4">
           The craftsmanship is extraordinary. You can feel the difference in quality the moment you put on a William Taylor piece. This is accessible luxury at its finest.
          </p>
          <div class="flex items-center gap-3">
           <div class="w-10 h-10 rounded-full overflow-hidden border border-wt-gold/30">
            <img alt="David K." class="w-full h-full object-cover object-top" src="/website/images/db23eed31_image.jpg"/>
           </div>
           <div>
            <p class="font-label text-xs font-semibold tracking-wider uppercase text-wt-oxblood">
             David K.
            </p>
            <p class="font-body text-xs text-gray-400 font-light">
             Nairobi, Kenya
            </p>
           </div>
          </div>
         </div>
         <div class="bg-white p-6 lg:p-8 relative" style="opacity: 0; transform: translateY(30px);">
          <span class="font-heading text-6xl text-wt-gold leading-none absolute top-4 left-6 opacity-30">
           "
          </span>
          <p class="font-body text-sm text-gray-600 font-light leading-relaxed mb-6 relative z-10 pt-4">
           I've worn designers from London to Milan, and William Taylor stands alongside them in quality, while being entirely its own thing. Distinctly Tanzanian, globally sophisticated.
          </p>
          <div class="flex items-center gap-3">
           <div class="w-10 h-10 rounded-full overflow-hidden border border-wt-gold/30">
            <img alt="Marcus A." class="w-full h-full object-cover object-top" src="/website/images/ee86da3ae_image.jpg"/>
           </div>
           <div>
            <p class="font-label text-xs font-semibold tracking-wider uppercase text-wt-oxblood">
             Marcus A.
            </p>
            <p class="font-body text-xs text-gray-400 font-light">
             Cape Town, South Africa
            </p>
           </div>
          </div>
         </div>
        </div>
       </div>
      </section>
@endif
      <section class="py-12 lg:py-20 bg-wt-offwhite">
       <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
        <div class="text-center mb-8 lg:mb-12">
         <div class="flex items-center justify-center gap-3 mb-3">
          <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 23px; height: 23px;">
           <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 16px; height: 16px;"/>
          </span>
          <svg class="lucide lucide-instagram text-wt-gold" fill="none" height="20" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
           <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
           </rect>
           <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
           </path>
           <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
           </line>
          </svg>
          <p class="font-label text-xs tracking-[0.3em] uppercase text-wt-gold">
           @williamtaylor
          </p>
         </div>
         <h2 class="section-title">
          Follow the Journey
         </h2>
        </div>
        <div class="grid grid-cols-3 md:grid-cols-6 gap-2">
         <a class="group relative aspect-square overflow-hidden" href="https://instagram.com/williamtaylor" rel="noopener noreferrer" style="opacity: 0;" target="_blank">
          <img alt="Instagram post 1" class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500" src="/website/images/0368482bc_image.jpg"/>
          <div class="absolute inset-0 bg-wt-oxblood/0 group-hover:bg-wt-oxblood/40 transition-colors duration-300 flex items-center justify-center">
           <svg class="lucide lucide-instagram text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
            <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
            </rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
            </path>
            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
            </line>
           </svg>
          </div>
         </a>
         <a class="group relative aspect-square overflow-hidden" href="https://instagram.com/williamtaylor" rel="noopener noreferrer" style="opacity: 0;" target="_blank">
          <img alt="Instagram post 2" class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500" src="/website/images/6d46d522f_image.jpg"/>
          <div class="absolute inset-0 bg-wt-oxblood/0 group-hover:bg-wt-oxblood/40 transition-colors duration-300 flex items-center justify-center">
           <svg class="lucide lucide-instagram text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
            <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
            </rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
            </path>
            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
            </line>
           </svg>
          </div>
         </a>
         <a class="group relative aspect-square overflow-hidden" href="https://instagram.com/williamtaylor" rel="noopener noreferrer" style="opacity: 0;" target="_blank">
          <img alt="Instagram post 3" class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500" src="/website/images/2946546dd_image.jpeg"/>
          <div class="absolute inset-0 bg-wt-oxblood/0 group-hover:bg-wt-oxblood/40 transition-colors duration-300 flex items-center justify-center">
           <svg class="lucide lucide-instagram text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
            <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
            </rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
            </path>
            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
            </line>
           </svg>
          </div>
         </a>
         <a class="group relative aspect-square overflow-hidden" href="https://instagram.com/williamtaylor" rel="noopener noreferrer" style="opacity: 0;" target="_blank">
          <img alt="Instagram post 4" class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500" src="/website/images/ee86da3ae_image.jpg"/>
          <div class="absolute inset-0 bg-wt-oxblood/0 group-hover:bg-wt-oxblood/40 transition-colors duration-300 flex items-center justify-center">
           <svg class="lucide lucide-instagram text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
            <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
            </rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
            </path>
            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
            </line>
           </svg>
          </div>
         </a>
         <a class="group relative aspect-square overflow-hidden" href="https://instagram.com/williamtaylor" rel="noopener noreferrer" style="opacity: 0;" target="_blank">
          <img alt="Instagram post 5" class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500" src="/website/images/db23eed31_image.jpg"/>
          <div class="absolute inset-0 bg-wt-oxblood/0 group-hover:bg-wt-oxblood/40 transition-colors duration-300 flex items-center justify-center">
           <svg class="lucide lucide-instagram text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
            <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
            </rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
            </path>
            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
            </line>
           </svg>
          </div>
         </a>
         <a class="group relative aspect-square overflow-hidden" href="https://instagram.com/williamtaylor" rel="noopener noreferrer" style="opacity: 0;" target="_blank">
          <img alt="Instagram post 6" class="w-full h-full object-cover object-top group-hover:scale-110 transition-transform duration-500" src="/website/images/9c691e236_image.jpg"/>
          <div class="absolute inset-0 bg-wt-oxblood/0 group-hover:bg-wt-oxblood/40 transition-colors duration-300 flex items-center justify-center">
           <svg class="lucide lucide-instagram text-white opacity-0 group-hover:opacity-100 transition-opacity duration-300" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
            <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
            </rect>
            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
            </path>
            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
            </line>
           </svg>
          </div>
         </a>
        </div>
        <div class="text-center mt-10">
         <a class="btn-outline px-10 py-4 inline-flex items-center gap-2" href="https://instagram.com/williamtaylor" rel="noopener noreferrer" target="_blank">
          <svg class="lucide lucide-instagram" fill="none" height="16" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="16" xmlns="http://www.w3.org/2000/svg">
           <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
           </rect>
           <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
           </path>
           <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
           </line>
          </svg>
          Follow @williamtaylor
         </a>
        </div>
       </div>
      </section>
     </div>
    </main>
    @include('frontend.partials.footer')
    @include('frontend.partials.mobile-bottom-navigation')
    @include('frontend.partials.whatsapp-action')
   </div>
   <div class="fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]">
    <div class="fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]">
    </div>
   </div>
  </div>

@if($homepageNewArrivals['managed'])
<template id="homepage-new-arrivals-projection">@include('frontend.partials.homepage-new-arrivals')</template>
@endif
@if($homepageHotSale['managed'])
<template id="homepage-hot-sale-projection">@include('frontend.partials.homepage-hot-sale')</template>
@endif
<template id="homepage-future-style-projection">@include('frontend.partials.homepage-future-style')</template>
<template id="homepage-limited-edition-projection">@include('frontend.partials.homepage-limited-edition')</template>
@if($homepageExploreCollectionsIsManaged)
<template id="homepage-explore-collections-projection">@include('frontend.partials.homepage-explore-collections')</template>
@endif
@if($homepageSummerEdit['managed'])
<template id="homepage-summer-edit-projection">@include('frontend.partials.homepage-summer-edit')</template>
@endif
@if($homepageDelivery['managed'])
<template id="homepage-delivery-projection">@include('frontend.partials.homepage-delivery')</template>
@endif
@if($homepageHandbags['managed'])
<template id="homepage-handbags-projection">@include('frontend.partials.homepage-handbags')</template>
<style>
[data-homepage-handbags] .wt-handbags-layout {display:grid;grid-template-columns:minmax(0,1fr);gap:24px}
[data-homepage-handbags] .wt-handbags-products {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
[data-homepage-handbags] .wt-handbags-layout > div {min-width:0}
@media(min-width:1024px) {
 [data-homepage-handbags] .wt-handbags-layout {grid-template-columns:repeat(12,minmax(0,1fr));gap:32px}
 [data-homepage-handbags] .wt-handbags-products {grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
}
</style>
@endif

@if($homepageClientStories['managed'])
<template id="homepage-client-stories-projection">@include('frontend.partials.homepage-client-stories')</template>
<style>
[data-homepage-client-stories] .wt-client-stories-grid {display:grid;grid-template-columns:minmax(0,1fr);gap:32px}
@media(min-width:768px) {[data-homepage-client-stories] .wt-client-stories-grid {grid-template-columns:repeat(3,minmax(0,1fr))}}
</style>
@endif
<script type="application/json" id="homepage-hero-data">@json($homepageHero)</script>
<script>
(() => {
 const dataNode = document.getElementById('homepage-hero-data');
 const root = document.getElementById('root');
 if (!dataNode || !root) return;
 const data = JSON.parse(dataNode.textContent);
 const setText = (node, value) => { if (node && node.textContent.trim() !== value) node.textContent = value; };
 const setAttribute = (node, name, value) => { if (node && node.getAttribute(name) !== value) node.setAttribute(name, value); };
 const synchronizeHero = () => {
  const hero = root.querySelector('main section');
  if (!hero) return;
  hero.dataset.homepageHero = '';
  const content = hero.querySelector(':scope > .relative.z-10');
  const background = hero.querySelector(':scope > div.absolute.inset-0');
  if (background) background.dataset.homepageHeroBackground = '';
  if (content) content.dataset.homepageHeroContent = '';
  const copy = content ? [...content.children].filter(node => node.matches('p, h1')) : [];
  setText(copy[0], data.eyebrow);
  setText(copy[1], data.title);
  setText(copy[2], data.subtitle);
  const actions = content?.querySelectorAll('a') || [];
  if (actions[0]?.parentElement) actions[0].parentElement.dataset.homepageHeroActions = '';
  setText(actions[0]?.lastChild, data.primary_cta_label);
  setAttribute(actions[0], 'href', data.primary_cta_url);
  setText(actions[1], data.secondary_cta_label);
  setAttribute(actions[1], 'href', data.secondary_cta_url);
  setAttribute(hero.querySelector(':scope > div.absolute.inset-0 img'), 'src', data.background_url);
  const scrollLabel = [...hero.querySelectorAll('span')].find(node => node.textContent.trim() === 'Scroll');
  if (scrollLabel?.parentElement) scrollLabel.parentElement.dataset.homepageHeroScroll = '';
  scrollLabel?.parentElement?.classList.toggle('hidden', !data.scroll_indicator_enabled);
  const indicator = hero.querySelector(':scope > div.absolute.right-4');
  if (indicator) indicator.dataset.homepageHeroIndicator = '';
 };
 const synchronizeNewArrivals = () => {
  const template = document.getElementById('homepage-new-arrivals-projection');
  if (!template) return;
  const projected = template.content.firstElementChild;
  if (!projected) return;
  const current = [...root.querySelectorAll('main section')].find(section => section.dataset.homepageNewArrivals !== undefined || section.querySelector('h2')?.textContent.trim() === 'New Arrivals');
  if (!current || current.dataset.homepageNewArrivalsSource === projected.dataset.homepageNewArrivalsSource) return;
  current.replaceWith(projected.cloneNode(true));
 };
 const synchronizeHotSale = () => {
  const template = document.getElementById('homepage-hot-sale-projection');
  if (!template) return;
  const projected = template.content.firstElementChild;
  if (!projected) return;
  const current = [...root.querySelectorAll('main section')].find(section => section.dataset.homepageHotSale !== undefined || section.querySelector('h2')?.textContent.trim() === "William's Hot Sale");
  if (!current || current.dataset.homepageHotSale !== undefined) return;
  current.replaceWith(projected.cloneNode(true));
 };
 const synchronizeFutureStyle = () => {
  const template = document.getElementById('homepage-future-style-projection');
  if (!template) return;
  const projected = template.content.firstElementChild;
  if (!projected) return;
  const current = [...root.querySelectorAll('main section')].find(section => section.dataset.homepageFutureStyle !== undefined || section.querySelector('h2')?.textContent.trim() === 'The Future of Style');
  if (!current || current.dataset.homepageFutureStyle !== undefined) return;
  current.replaceWith(projected.cloneNode(true));
 };
 const synchronizeLimitedEdition = () => {
  const template = document.getElementById('homepage-limited-edition-projection');
  if (!template) return;
  const projected = template.content.firstElementChild;
  if (!projected) return;
  const current = [...root.querySelectorAll('main section')].find(section => section.dataset.homepageLimitedEdition !== undefined || section.querySelector('h2')?.textContent.trim() === 'LIMITED EDITION');
  if (!current || current.dataset.homepageLimitedEdition !== undefined) return;
  current.replaceWith(projected.cloneNode(true));
 };
 const synchronizeExploreCollections = () => {
  const template = document.getElementById('homepage-explore-collections-projection');
  if (!template) return;
  const projected = template.content.firstElementChild;
  if (!projected) return;
  const current = [...root.querySelectorAll('main section')].find(section => section.dataset.homepageExploreCollections !== undefined || section.querySelector('h2')?.textContent.trim() === 'Explore the Collection');
  if (!current || current.dataset.homepageExploreCollections !== undefined) return;
  current.replaceWith(projected.cloneNode(true));
 };
 const synchronizeSummerEdit = () => {
  const projected = document.getElementById('homepage-summer-edit-projection')?.content.firstElementChild;
  if (!projected) return;
  const current = root.querySelector('[data-homepage-summer-edit]') || [...root.querySelectorAll('h2')].find(node => node.textContent.trim() === 'The Summer Edit')?.closest('.relative.z-10.px-4');
  if (!current || current.hasAttribute('data-homepage-summer-edit')) return;
  current.replaceWith(projected.cloneNode(true));
 };
 const synchronizeDelivery = () => {
  const projected = document.getElementById('homepage-delivery-projection')?.content.firstElementChild;
  if (!projected) return;
  const current = root.querySelector('[data-homepage-delivery]') || [...root.querySelectorAll('h3')].find(node => node.textContent.trim() === 'Complimentary Delivery in Dar es Salaam')?.closest('.relative.z-10.bg-wt-cream');
  if (!current || current.hasAttribute('data-homepage-delivery')) return;
  current.replaceWith(projected.cloneNode(true));
 };
 const synchronizeHandbags = () => {
  const projected = document.getElementById('homepage-handbags-projection')?.content.firstElementChild;
  if (!projected) return;
  const current = root.querySelector('[data-homepage-handbags]') || [...root.querySelectorAll('h2')].find(node => node.textContent.trim() === "Women's Handbags")?.closest('section');
  if (!current || current.hasAttribute('data-homepage-handbags')) return;
  current.replaceWith(projected.cloneNode(true));
 };
 const synchronizeClientStories = () => {
  const projected = document.getElementById('homepage-client-stories-projection')?.content.firstElementChild;
  if (!projected) return;
  const current = root.querySelector('[data-homepage-client-stories]') || [...root.querySelectorAll('h2')].find(node => node.textContent.trim() === 'What They Say')?.closest('section');
  if (!current || current.hasAttribute('data-homepage-client-stories')) return;
  current.replaceWith(projected.cloneNode(true));
 };
 const synchronize = () => { synchronizeHero(); synchronizeNewArrivals(); synchronizeHotSale(); synchronizeFutureStyle(); synchronizeLimitedEdition(); synchronizeExploreCollections(); synchronizeSummerEdit(); synchronizeDelivery(); synchronizeHandbags(); synchronizeClientStories(); };
 const observer = new MutationObserver(synchronize);
 observer.observe(root, {childList: true, subtree: true});
 window.addEventListener('load', () => requestAnimationFrame(() => requestAnimationFrame(synchronize)), {once: true});
 synchronize();
})();
</script>
@include('frontend.partials.homepage-client-stories-script')
@include('frontend.partials.homepage-handbags-script')
@include('frontend.partials.pre-order-countdown-script')
@endsection
