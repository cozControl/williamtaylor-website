@extends('layouts.frontend')

@section('route-bootstrap')
  <script>
   if (window.location.pathname === '/limited-edition') {
    const routeReplaceState = History.prototype.replaceState;
    routeReplaceState.call(window.history, window.history.state, '', '/collections/limited-edition');
    window.addEventListener('load', function () {
     window.setTimeout(function () {
      routeReplaceState.call(window.history, window.history.state, '', '/limited-edition');
     }, 1500);
    }, { once: true });
   }
  </script>
@endsection

@section('content')
  <div id="root">
   <div class="min-h-screen flex flex-col bg-wt-offwhite">
    <header class="fixed top-0 left-0 right-0 z-40">
     @include('frontend.partials.announcement')
     <nav class="frosted-nav transition-all duration-300 relative" style="box-shadow: rgba(0, 0, 0, 0.25) 0px 4px 30px;">
      <div class="max-w-screen-2xl mx-auto px-4 lg:px-12 h-14 lg:h-16 flex items-center justify-between gap-4">
       <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
        <button class="lg:hidden text-wt-cream hover:text-wt-gold transition-colors" @if($publicSiteChrome?->navigation) data-public-menu-open aria-label="Open menu" aria-controls="public-mobile-navigation" aria-expanded="false" @endif>
         <svg class="lucide lucide-menu" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
          <line x1="4" x2="20" y1="12" y2="12">
          </line>
          <line x1="4" x2="20" y1="6" y2="6">
          </line>
          <line x1="4" x2="20" y1="18" y2="18">
          </line>
         </svg>
        </button>
        <button class="lg:hidden text-wt-cream hover:text-wt-gold transition-colors">
         <svg class="lucide lucide-chevron-left" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
          <path d="m15 18-6-6 6-6">
          </path>
         </svg>
        </button>
        <a class="flex-shrink-0" href="/">
         <img alt="{{ $publicSiteChrome?->profile?->brandName ?: 'William Taylor' }}" class="h-5 lg:h-7 w-auto mix-blend-screen" src="{{ $publicSiteChrome?->profile?->headerLogoUrl ?: '/website/images/8d99836ea_LOGO-3.png' }}"/>
        </a>
       </div>
       <div class="lg:hidden flex-1 text-center min-w-0 px-2">
        <span class="font-heading text-sm text-wt-cream tracking-wide truncate block">
         Collection
        </span>
       </div>
       @if($publicSiteChrome?->navigation)
       <div class="hidden lg:flex items-center gap-8">
        @foreach($publicSiteChrome->navigation->items as $item)
         @if($item->visibility !== 'mobile')
         <div class="relative">
          <a class="font-label text-xs tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors duration-200" href="{{ $item->link->url }}" @if($item->link->newTab) target="_blank" rel="noopener noreferrer" @endif>{{ $item->link->label }}</a>
         </div>
         @endif
        @endforeach
       </div>
       @else       <div class="hidden lg:flex items-center gap-8">
        <div class="relative">
         <button class="flex items-center gap-1 font-label text-xs tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors duration-200">
          Shop
          <svg class="lucide lucide-chevron-down" fill="none" height="12" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="12" xmlns="http://www.w3.org/2000/svg">
           <path d="m6 9 6 6 6-6">
           </path>
          </svg>
         </button>
        </div>
        <div class="relative">
         <a class="font-label text-xs tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors duration-200" href="{{ route('collections.index') }}">
          Collections
         </a>
        </div>
        <div class="relative">
         <a class="font-label text-xs tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors duration-200" href="{{ route('products.index', ['sort' => 'newest']) }}">
          New Arrivals
         </a>
        </div>
        <div class="relative">
         <a class="font-label text-xs tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors duration-200" href="{{ route('preorders.index') }}">
          Pre-Order
         </a>
        </div>
        <div class="relative">
         <a class="font-label text-xs tracking-widest uppercase text-wt-cream hover:text-wt-gold transition-colors duration-200" href="{{ route('limited-edition.index') }}">
          Limited Edition
         </a>
        </div>
       </div>
       @endif
       <div class="flex items-center gap-2 sm:gap-3 lg:gap-4 flex-shrink-0">
        <div class="hidden sm:block">
         <button class="hidden lg:flex items-center border border-wt-gold/30 rounded-full overflow-hidden" title="Toggle currency">
          <span class="px-2.5 py-1 font-label text-[10px] tracking-wider uppercase transition-colors bg-wt-gold text-wt-oxblood">
           TZS
          </span>
          <span class="px-2.5 py-1 font-label text-[10px] tracking-wider uppercase transition-colors text-wt-cream/60">
           USD
          </span>
         </button>
        </div>
        <button class="text-wt-cream hover:text-wt-gold transition-colors">
         <svg class="lucide lucide-search" fill="none" height="20" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
          <circle cx="11" cy="11" r="8">
          </circle>
          <path d="m21 21-4.3-4.3">
          </path>
         </svg>
        </button>
        <a class="text-wt-cream hover:text-wt-gold transition-colors hidden lg:block" href="{{ route('gift-cards.index') }}" title="Gift Cards">
         <svg class="lucide lucide-gift" fill="none" height="20" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
          <rect height="4" rx="1" width="18" x="3" y="8">
          </rect>
          <path d="M12 8v13">
          </path>
          <path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7">
          </path>
          <path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5">
          </path>
         </svg>
        </a>
        <a class="text-wt-cream hover:text-wt-gold transition-colors hidden lg:block" href="{{ route('login') }}" title="My Account">
         <svg class="lucide lucide-user" fill="none" height="20" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
          <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2">
          </path>
          <circle cx="12" cy="7" r="4">
          </circle>
         </svg>
        </a>
        <a class="relative hidden lg:block text-wt-cream hover:text-wt-gold transition-colors" href="{{ route('wishlist.index') }}">
         <svg class="lucide lucide-heart" fill="none" height="20" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
          <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
          </path>
         </svg>
        </a>
        <button class="relative text-wt-cream hover:text-wt-gold transition-colors">
         <svg class="lucide lucide-shopping-bag" fill="none" height="20" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
          <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z">
          </path>
          <path d="M3 6h18">
          </path>
          <path d="M16 10a4 4 0 0 1-8 0">
          </path>
         </svg>
        </button>
       </div>
      </div>
     </nav>
     @include('frontend.partials.projected-mobile-navigation')
    </header>
    <main class="flex-1 pt-14 lg:pt-16 pb-16 lg:pb-0">
     <div class="min-h-screen pt-0 bg-wt-oxblood">
      <div class="py-16 px-6 text-center">
       <p class="font-label text-xs tracking-[0.3em] uppercase text-wt-gold mb-3">
        Exclusive
       </p>
       <h1 class="font-heading text-5xl text-wt-cream">
        Limited Edition
       </h1>
       <p class="font-body text-sm text-wt-cream/60 font-light mt-3 max-w-md mx-auto">
        Numbered pieces. Exclusive access.
       </p>
      </div>
      <div class="max-w-screen-xl mx-auto px-6 lg:px-12 py-16">
       <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
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
        <div class="group relative" style="opacity: 1; transform: none;">
         <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
          <a href="/product/heritage-blazer">
           <img alt="The Heritage Blazer" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/9cf5386c6_image.jpg"/>
          </a>
          <div class="absolute top-3 left-3 flex flex-col gap-1">
           <span class="bg-wt-gold text-wt-oxblood font-label px-2 py-0.5 text-[10px] tracking-widest">
            LIMITED
           </span>
          </div>
          <div class="absolute bottom-3 left-3">
           <span class="bg-wt-gold text-wt-oxblood font-label text-[10px] tracking-widest px-2 py-0.5">
            Only 40 Made
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
           <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(107, 142, 122);" title="Sage Green">
           </div>
           <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(196, 154, 108);" title="Camel">
           </div>
          </div>
          <a href="/product/heritage-blazer">
           <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
            The Heritage Blazer
           </h3>
          </a>
          <p class="font-label text-sm text-wt-oxblood mt-1">
           <span class="inline-flex items-center gap-1.5">
            <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
             <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
            </span>
            <span>
             TZS 750,000
            </span>
           </span>
          </p>
          <p class="font-label text-[10px] tracking-wider text-red-500 uppercase mt-0.5">
           Only 8 left
          </p>
         </div>
        </div>
        <div class="group relative" style="opacity: 1; transform: none;">
         <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
          <a href="/product/cashmere-blend-scarf">
           <img alt="Cashmere Blend Scarf" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/b9c0b1bdf_image.jpg"/>
          </a>
          <div class="absolute top-3 left-3 flex flex-col gap-1">
           <span class="bg-wt-gold text-wt-oxblood font-label px-2 py-0.5 text-[10px] tracking-widest">
            LIMITED
           </span>
          </div>
          <div class="absolute bottom-3 left-3">
           <span class="bg-wt-gold text-wt-oxblood font-label text-[10px] tracking-widest px-2 py-0.5">
            Only 25 Made
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
          <a href="/product/cashmere-blend-scarf">
           <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
            Cashmere Blend Scarf
           </h3>
          </a>
          <p class="font-label text-sm text-wt-oxblood mt-1">
           <span class="inline-flex items-center gap-1.5">
            <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
             <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
            </span>
            <span>
             TZS 320,000
            </span>
           </span>
          </p>
         </div>
        </div>
        <div class="group relative" style="opacity: 1; transform: none;">
         <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
          <a href="/product/drape-tank-set">
           <img alt="The Drape Tank Set" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/93216c40a_image.jpg"/>
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
          <div class="flex gap-1 mb-2">
           <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(184, 132, 90);" title="Camel">
           </div>
           <div class="w-3 h-3 rounded-full border border-gray-300" style="background-color: rgb(255, 255, 255);" title="White">
           </div>
          </div>
          <a href="/product/drape-tank-set">
           <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
            The Drape Tank Set
           </h3>
          </a>
          <p class="font-label text-sm text-wt-oxblood mt-1">
           <span class="inline-flex items-center gap-1.5">
            <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
             <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
            </span>
            <span>
             TZS 385,000
            </span>
           </span>
          </p>
         </div>
        </div>
        <div class="group relative" style="opacity: 1; transform: none;">
         <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
          <a href="/product/dar-bag-mini">
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
          <a href="/product/dar-bag-mini">
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
      </div>
     </div>
    </main>
    @include('frontend.partials.footer')
    <div class="lg:hidden fixed bottom-0 left-0 right-0 z-40">
     <div class="frosted-nav border-t border-wt-gold/20">
      <div class="flex items-center justify-around h-16 px-2">
       <a class="relative flex flex-col items-center justify-center gap-0.5 flex-1 h-full" href="/">
        <div class="relative">
         <svg class="lucide lucide-house text-wt-cream/60" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
          <path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8">
          </path>
          <path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z">
          </path>
         </svg>
        </div>
        <span class="font-label text-[9px] tracking-wide uppercase text-wt-cream/50">
         Home
        </span>
       </a>
       <a class="relative flex flex-col items-center justify-center gap-0.5 flex-1 h-full" href="{{ route('products.index') }}">
        <div class="relative">
         <svg class="lucide lucide-grid3x3 text-wt-cream/60" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
          <rect height="18" rx="2" width="18" x="3" y="3">
          </rect>
          <path d="M3 9h18">
          </path>
          <path d="M3 15h18">
          </path>
          <path d="M9 3v18">
          </path>
          <path d="M15 3v18">
          </path>
         </svg>
        </div>
        <span class="font-label text-[9px] tracking-wide uppercase text-wt-cream/50">
         Shop
        </span>
       </a>
       <a class="relative flex flex-col items-center justify-center gap-0.5 flex-1 h-full" href="{{ route('products.index') }}">
        <div class="relative">
         <svg class="lucide lucide-search text-wt-cream/60" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
          <circle cx="11" cy="11" r="8">
          </circle>
          <path d="m21 21-4.3-4.3">
          </path>
         </svg>
        </div>
        <span class="font-label text-[9px] tracking-wide uppercase text-wt-cream/50">
         Search
        </span>
       </a>
       <a class="relative flex flex-col items-center justify-center gap-0.5 flex-1 h-full" href="{{ route('wishlist.index') }}">
        <div class="relative">
         <svg class="lucide lucide-heart text-wt-cream/60" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
          <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
          </path>
         </svg>
        </div>
        <span class="font-label text-[9px] tracking-wide uppercase text-wt-cream/50">
         Wishlist
        </span>
       </a>
       <a class="relative flex flex-col items-center justify-center gap-0.5 flex-1 h-full" href="/cart">
        <div class="relative">
         <svg class="lucide lucide-shopping-bag text-wt-cream/60" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
          <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z">
          </path>
          <path d="M3 6h18">
          </path>
          <path d="M16 10a4 4 0 0 1-8 0">
          </path>
         </svg>
        </div>
        <span class="font-label text-[9px] tracking-wide uppercase text-wt-cream/50">
         Cart
        </span>
       </a>
      </div>
     </div>
    </div>
    @include('frontend.partials.whatsapp-action')
   </div>
   <div class="fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]">
    <div class="fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]">
    </div>
   </div>
  </div>

@endsection
