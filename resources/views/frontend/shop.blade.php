@extends('layouts.frontend')

@section('content')
  <div id="root">
   <div class="min-h-screen flex flex-col bg-wt-offwhite">
    @include('frontend.partials.header')
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
    @include('frontend.partials.whatsapp-action')
   </div>
   <div class="fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]">
    <div class="fixed top-0 z-[100] flex max-h-screen w-full flex-col-reverse p-4 sm:bottom-0 sm:right-0 sm:top-auto sm:flex-col md:max-w-[420px]">
    </div>
   </div>
  </div>

@endsection
