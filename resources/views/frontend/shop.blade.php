@extends('layouts.frontend')

@section('content')
  <div id="root">
   <div class="min-h-screen flex flex-col bg-wt-offwhite">
    <header class="fixed top-0 left-0 right-0 z-40">
     <div class="overflow-hidden transition-all duration-300 max-h-0 opacity-0">
      <div class="bg-wt-oxblood text-wt-cream py-2 px-8 text-center relative z-50 overflow-hidden">
       <div aria-hidden="true" class="absolute inset-0 wt-pattern-texture pointer-events-none" style='background-image: url("/website/images/eab6bab5d_bg.jpg"); background-size: 120px; opacity: 0.05;'>
       </div>
       <p class="relative font-label text-[10px] sm:text-xs tracking-widest uppercase truncate">
        <span class="hidden sm:inline">
         Free Shipping on Orders Over TZS 500,000  ·  Express Delivery in Dar es Salaam
        </span>
        <span class="sm:hidden">
         Free Shipping Over TZS 500,000 · Express in Dar
        </span>
       </p>
       <button aria-label="Close announcement" class="absolute right-3 top-1/2 -translate-y-1/2 text-wt-cream/60 hover:text-wt-cream transition-colors">
        <svg class="lucide lucide-x" fill="none" height="14" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="14" xmlns="http://www.w3.org/2000/svg">
         <path d="M18 6 6 18">
         </path>
         <path d="m6 6 12 12">
         </path>
        </svg>
       </button>
      </div>
     </div>
     <nav class="frosted-nav transition-all duration-300 relative" style="box-shadow: rgba(0, 0, 0, 0.25) 0px 4px 30px;">
      <div class="max-w-screen-2xl mx-auto px-4 lg:px-12 h-14 lg:h-16 flex items-center justify-between gap-4">
       <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
        <button class="lg:hidden text-wt-cream hover:text-wt-gold transition-colors">
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
         <img alt="William Taylor" class="h-5 lg:h-7 w-auto mix-blend-screen" src="/website/images/8d99836ea_LOGO-3.png"/>
        </a>
       </div>
       <div class="lg:hidden flex-1 text-center min-w-0 px-2">
        <span class="font-heading text-sm text-wt-cream tracking-wide truncate block">
         Shop
        </span>
       </div>
       <div class="hidden lg:flex items-center gap-8">
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
    </header>
    <main class="flex-1 pt-14 lg:pt-16 pb-16 lg:pb-0">
     <div class="min-h-screen bg-wt-offwhite pt-0">
      <div class="relative bg-wt-oxblood py-16 px-6 text-center overflow-hidden">
       <div aria-hidden="true" class="absolute inset-0 wt-pattern-texture wt-pattern-breathe pointer-events-none" style='background-image: url("/website/images/eab6bab5d_bg.jpg"); background-size: 300px;'>
       </div>
       <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold/30 to-transparent">
       </div>
       <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold/30 to-transparent">
       </div>
       <div class="relative z-10">
        <img alt="" aria-hidden="true" class="mix-blend-screen inline-block object-contain flex-shrink-0 mb-4" src="/website/images/cf030fe26_ICONlight.png" style="width: 22px; height: 22px;"/>
        <p class="font-label text-xs tracking-[0.3em] uppercase text-wt-gold mb-3">
         The Atelier
        </p>
        <h1 class="font-heading text-5xl text-wt-cream">
         Shop All
        </h1>
        <p class="font-body text-sm text-wt-cream/60 font-light mt-3">
         24 pieces curated for the modern gentleman
        </p>
       </div>
      </div>
      <div class="max-w-screen-xl mx-auto px-6 lg:px-12 py-10">
       <div class="flex items-center justify-between mb-8 gap-4">
        <button class="flex items-center gap-2 border border-wt-oxblood text-wt-oxblood px-4 py-2 font-label text-xs tracking-widest uppercase hover:bg-wt-oxblood hover:text-wt-cream transition-all">
         <svg class="lucide lucide-sliders-horizontal" fill="none" height="14" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="14" xmlns="http://www.w3.org/2000/svg">
          <line x1="21" x2="14" y1="4" y2="4">
          </line>
          <line x1="10" x2="3" y1="4" y2="4">
          </line>
          <line x1="21" x2="12" y1="12" y2="12">
          </line>
          <line x1="8" x2="3" y1="12" y2="12">
          </line>
          <line x1="21" x2="16" y1="20" y2="20">
          </line>
          <line x1="12" x2="3" y1="20" y2="20">
          </line>
          <line x1="14" x2="14" y1="2" y2="6">
          </line>
          <line x1="8" x2="8" y1="10" y2="14">
          </line>
          <line x1="16" x2="16" y1="18" y2="22">
          </line>
         </svg>
         Filters
        </button>
        <div class="flex items-center gap-3 ml-auto">
         <span class="font-label text-xs text-gray-400 uppercase tracking-wider hidden sm:block">
          24 Results
         </span>
         <select class="border border-gray-200 text-wt-oxblood px-3 py-2 font-label text-xs tracking-wider uppercase outline-none bg-white">
          <option value="featured">
           Featured
          </option>
          <option value="newest">
           Newest First
          </option>
          <option value="price-asc">
           Price: Low to High
          </option>
          <option value="price-desc">
           Price: High to Low
          </option>
          <option value="bestselling">
           Best Selling
          </option>
         </select>
         <div class="hidden sm:flex gap-1">
          <button class="p-2 border transition-all bg-wt-oxblood text-wt-cream border-wt-oxblood">
           <svg class="lucide lucide-grid3x3" fill="none" height="14" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="14" xmlns="http://www.w3.org/2000/svg">
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
          </button>
          <button class="p-2 border transition-all border-gray-200 text-gray-400 hover:border-wt-oxblood">
           <svg class="lucide lucide-layout-list" fill="none" height="14" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="14" xmlns="http://www.w3.org/2000/svg">
            <rect height="7" rx="1" width="7" x="3" y="3">
            </rect>
            <rect height="7" rx="1" width="7" x="3" y="14">
            </rect>
            <path d="M14 4h7">
            </path>
            <path d="M14 9h7">
            </path>
            <path d="M14 15h7">
            </path>
            <path d="M14 20h7">
            </path>
           </svg>
          </button>
         </div>
        </div>
       </div>
       <div class="flex gap-8">
        <div class="flex-1">
         <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
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
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/zanzibar-resort-shirt">
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
            <a href="/product/zanzibar-resort-shirt">
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
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/signature-vest-blazer">
             <img alt="The Signature Vest Blazer" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/c0cb4e750_image.jpg"/>
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
            <a href="/product/signature-vest-blazer">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              The Signature Vest Blazer
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 465,000
              </span>
             </span>
            </p>
           </div>
          </div>
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/savanna-tote-bag">
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
            <a href="/product/savanna-tote-bag">
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
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/kilimanjaro-clutch">
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
            <a href="/product/kilimanjaro-clutch">
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
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/mwanza-satchel">
             <img alt="The Mwanza Satchel" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/7e8ceffc2_generated_image.png"/>
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
            <a href="/product/mwanza-satchel">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              The Mwanza Satchel
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
            <a href="/product/unisex-oversized-tee">
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
            <a href="/product/unisex-oversized-tee">
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
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/relaxed-fit-denim-jacket">
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
            <a href="/product/relaxed-fit-denim-jacket">
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
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/performance-joggers">
             <img alt="Performance Joggers" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/98e3f82c0_image.jpg"/>
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
            <a href="/product/performance-joggers">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              Performance Joggers
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 175,000
              </span>
             </span>
            </p>
           </div>
          </div>
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/summer-linen-trousers">
             <img alt="Summer Linen Trousers" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/601f1442e_image.jpg"/>
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
            <a href="/product/summer-linen-trousers">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              Summer Linen Trousers
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
            <p class="font-label text-xs tracking-wider text-wt-gold uppercase mt-0.5">
             Pre-Order · Ships 2026-08-01
            </p>
           </div>
          </div>
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/silk-pocket-square-set">
             <img alt="Silk Pocket Square Set" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/9c30daca8_image.jpg"/>
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
            <a href="/product/silk-pocket-square-set">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              Silk Pocket Square Set
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 85,000
              </span>
             </span>
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
            <a href="/product/monogram-leather-wallet">
             <img alt="Monogram Leather Wallet" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/da608a583_image.jpg"/>
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
            <a href="/product/monogram-leather-wallet">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              Monogram Leather Wallet
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 210,000
              </span>
             </span>
            </p>
           </div>
          </div>
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/serengeti-shoulder-bag">
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
            <a href="/product/serengeti-shoulder-bag">
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
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/zanzibar-crossbody">
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
            <a href="/product/zanzibar-crossbody">
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
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/arusha-bucket-bag">
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
            <a href="/product/arusha-bucket-bag">
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
          <div class="group relative" style="opacity: 1; transform: none;">
           <div class="relative overflow-hidden bg-gray-100 aspect-[3/4]">
            <a href="/product/tanga-woven-bag">
             <img alt="The Tanga Woven Bag" class="w-full h-full object-cover object-top transition-transform duration-700 group-hover:scale-105" src="/website/images/c54010737_generated_image.png"/>
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
            <a href="/product/tanga-woven-bag">
             <h3 class="font-heading text-base text-wt-oxblood leading-snug hover:text-wt-gold transition-colors">
              The Tanga Woven Bag
             </h3>
            </a>
            <p class="font-label text-sm text-wt-oxblood mt-1">
             <span class="inline-flex items-center gap-1.5">
              <span aria-hidden="true" class="inline-flex items-center justify-center bg-wt-oxblood rounded-full flex-shrink-0" style="width: 16px; height: 16px;">
               <img alt="" class="mix-blend-screen object-contain" src="/website/images/cf030fe26_ICONlight.png" style="width: 11px; height: 11px;"/>
              </span>
              <span>
               TZS 165,000
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
    </main>
    <footer class="bg-wt-oxblood border-t border-wt-gold/30 pb-16 lg:pb-0 relative overflow-hidden">
     <div aria-hidden="true" class="absolute inset-0 pointer-events-none wt-footer-pattern" style='background-image: url("/website/images/eab6bab5d_bg.jpg"); background-size: auto;'>
     </div>
     <div class="bg-wt-oxblood py-16 px-6">
      <div class="max-w-xl mx-auto text-center">
       <p class="font-label text-xs tracking-widest uppercase text-wt-gold mb-4">
        Inner Circle
       </p>
       <h2 class="font-heading text-3xl text-wt-cream mb-3">
        Sign the Ledger
       </h2>
       <p class="font-body text-sm text-wt-cream/60 font-light mb-8">
        Be the first to access Limited Editions, private sales, and the latest from the atelier.
       </p>
       <form class="flex gap-0 max-w-sm mx-auto">
        <input class="flex-1 bg-white/10 border border-wt-gold/40 text-wt-cream placeholder-wt-cream/40 px-4 py-3 font-body text-sm outline-none focus:border-wt-gold transition-colors" placeholder="Your email address" required="" type="email" value=""/>
        <button class="btn-gold px-6 py-3 text-xs" type="submit">
         Join
        </button>
       </form>
      </div>
     </div>
     <div class="max-w-screen-xl mx-auto px-6 lg:px-12 py-16 relative z-10">
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-12 gap-10 lg:gap-8">
       <div class="col-span-2 md:col-span-3 lg:col-span-4">
        <img alt="William Taylor" class="h-9 w-auto max-w-[170px] object-contain mb-5 mix-blend-screen" src="/website/images/8d99836ea_LOGO-3.png"/>
        <p class="font-body text-sm text-wt-cream/70 font-light leading-relaxed mb-6 max-w-xs">
         Crafted for the Modern Gentleman. Contemporary menswear designed in Tanzania, worn worldwide.
        </p>
        <div class="flex items-center gap-3">
         <a class="w-9 h-9 border border-wt-gold/25 flex items-center justify-center text-wt-cream/50 hover:text-wt-oxblood hover:bg-wt-gold hover:border-wt-gold transition-all duration-300" href="https://instagram.com/williamtaylor" rel="noopener noreferrer" target="_blank">
          <svg class="lucide lucide-instagram" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
           <rect height="20" rx="5" ry="5" width="20" x="2" y="2">
           </rect>
           <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z">
           </path>
           <line x1="17.5" x2="17.51" y1="6.5" y2="6.5">
           </line>
          </svg>
         </a>
         <a class="w-9 h-9 border border-wt-gold/25 flex items-center justify-center text-wt-cream/50 hover:text-wt-oxblood hover:bg-wt-gold hover:border-wt-gold transition-all duration-300" href="https://facebook.com" rel="noopener noreferrer" target="_blank">
          <svg class="lucide lucide-facebook" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
           <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z">
           </path>
          </svg>
         </a>
         <a class="w-9 h-9 border border-wt-gold/25 flex items-center justify-center text-wt-cream/50 hover:text-wt-oxblood hover:bg-wt-gold hover:border-wt-gold transition-all duration-300" href="https://twitter.com" rel="noopener noreferrer" target="_blank">
          <svg class="lucide lucide-twitter" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
           <path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2C3 15.5.5 9.6 3 5c2.2 2.6 5.6 4.1 9 4-.9-4.2 4-6.6 7-3.8 1.1 0 3-1.2 3-1.2z">
           </path>
          </svg>
         </a>
         <a class="w-9 h-9 border border-wt-gold/25 flex items-center justify-center text-wt-cream/50 hover:text-wt-oxblood hover:bg-wt-gold hover:border-wt-gold transition-all duration-300" href="https://youtube.com" rel="noopener noreferrer" target="_blank">
          <svg class="lucide lucide-youtube" fill="none" height="15" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="15" xmlns="http://www.w3.org/2000/svg">
           <path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17">
           </path>
           <path d="m10 15 5-3-5-3z">
           </path>
          </svg>
         </a>
        </div>
       </div>
       <div class="lg:col-span-2">
        <div class="mb-5">
         <h4 class="font-label text-xs tracking-widest uppercase text-wt-gold mb-2">
          Shop
         </h4>
         <div class="w-6 h-px bg-wt-gold/40">
         </div>
        </div>
        <ul class="space-y-2.5">
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/collections/mens-wear">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Men's Wear
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/collections/unisex">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Unisex
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/collections/accessories">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Accessories
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="{{ route('limited-edition.index') }}">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Limited Edition
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="{{ route('preorders.index') }}">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Pre-Order
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="{{ route('products.index', ['sort' => 'newest']) }}">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           New Arrivals
          </a>
         </li>
        </ul>
       </div>
       <div class="lg:col-span-2">
        <div class="mb-5">
         <h4 class="font-label text-xs tracking-widest uppercase text-wt-gold mb-2">
          Atelier
         </h4>
         <div class="w-6 h-px bg-wt-gold/40">
         </div>
        </div>
        <ul class="space-y-2.5">
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/about">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           About Us
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/membership">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Membership
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="{{ route('gift-cards.index') }}">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Gift Cards
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="{{ route('login') }}">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           My Account
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/track-order">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Track Order
          </a>
         </li>
        </ul>
       </div>
       <div class="lg:col-span-2">
        <div class="mb-5">
         <h4 class="font-label text-xs tracking-widest uppercase text-wt-gold mb-2">
          Support
         </h4>
         <div class="w-6 h-px bg-wt-gold/40">
         </div>
        </div>
        <ul class="space-y-2.5">
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/contact">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Contact Us
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/shipping">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Shipping
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/returns">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Returns
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/size-guide">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           Size Guide
          </a>
         </li>
         <li>
          <a class="group inline-flex items-center gap-2 font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="/faq">
           <span class="w-0 h-px bg-wt-gold transition-all duration-300 group-hover:w-3">
           </span>
           FAQ
          </a>
         </li>
        </ul>
       </div>
       <div class="col-span-2 md:col-span-1 lg:col-span-2">
        <div class="mb-5">
         <h4 class="font-label text-xs tracking-widest uppercase text-wt-gold mb-2">
          Visit
         </h4>
         <div class="w-6 h-px bg-wt-gold/40">
         </div>
        </div>
        <ul class="space-y-3">
         <li class="font-body text-sm text-wt-cream/70 font-light leading-relaxed">
          Dar Village Mall
          <br/>
          Dar Es Salaam, Tanzania
         </li>
         <li>
          <a class="font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="mailto:info@williamtaylor.co.tz">
           info@williamtaylor.co.tz
          </a>
         </li>
         <li>
          <a class="font-body text-sm text-wt-cream/70 hover:text-wt-gold font-light transition-colors" href="tel:+255656464876">
           +255 656 464 876
          </a>
         </li>
        </ul>
        <div class="flex flex-wrap gap-x-3 gap-y-1 mt-5">
         <a class="font-body text-xs text-wt-cream/40 hover:text-wt-gold transition-colors font-light" href="/privacy">
          Privacy
         </a>
         <span class="text-wt-cream/20">
          ·
         </span>
         <a class="font-body text-xs text-wt-cream/40 hover:text-wt-gold transition-colors font-light" href="/terms">
          Terms
         </a>
         <span class="text-wt-cream/20">
          ·
         </span>
         <a class="font-body text-xs text-wt-cream/40 hover:text-wt-gold transition-colors font-light" href="/admin">
          Admin
         </a>
        </div>
       </div>
      </div>
      <div class="mt-12 pt-8 border-t border-wt-gold/20 flex flex-col md:flex-row items-center justify-between gap-4">
       <p class="font-label text-xs tracking-wider text-wt-cream/50 uppercase">
        © 2026 William Taylor. All Rights Reserved.
       </p>
       <div class="flex items-center gap-3">
        <span class="font-label text-xs tracking-wider text-wt-cream/40 uppercase">
         We Accept
        </span>
        <span class="border border-wt-cream/20 px-2 py-1 font-label text-[10px] tracking-wider text-wt-cream/50 uppercase">
         Lipa Number
        </span>
        <span class="border border-wt-cream/20 px-2 py-1 font-label text-[10px] tracking-wider text-wt-cream/50 uppercase">
         M-Pesa
        </span>
        <span class="border border-wt-cream/20 px-2 py-1 font-label text-[10px] tracking-wider text-wt-cream/50 uppercase">
         Visa
        </span>
        <span class="border border-wt-cream/20 px-2 py-1 font-label text-[10px] tracking-wider text-wt-cream/50 uppercase">
         Mastercard
        </span>
       </div>
      </div>
     </div>
    </footer>
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
         <svg class="lucide lucide-grid3x3 text-wt-gold" fill="none" height="22" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" viewbox="0 0 24 24" width="22" xmlns="http://www.w3.org/2000/svg">
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
        <span class="font-label text-[9px] tracking-wide uppercase text-wt-gold font-semibold">
         Shop
        </span>
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-8 h-0.5 bg-wt-gold" style="opacity: 1;">
        </div>
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
    <a aria-label="Chat on WhatsApp" class="fixed bottom-20 lg:bottom-6 right-4 lg:right-6 z-30 w-12 h-12 lg:w-14 lg:h-14 bg-wt-gold shadow-xl flex items-center justify-center hover:bg-wt-oxblood transition-colors duration-300 group" href="https://wa.me/255656464876?text=Hello%20William%20Taylor%2C%20I%20have%20an%20enquiry." rel="noopener noreferrer" target="_blank">
     <svg class="lucide lucide-message-circle text-wt-oxblood group-hover:text-wt-cream transition-colors" fill="none" height="24" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg">
      <path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z">
      </path>
     </svg>
    </a>
   </div>
   <div class="fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]">
    <div class="fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]">
    </div>
   </div>
  </div>

@endsection
