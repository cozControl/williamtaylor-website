@extends('layouts.frontend')

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
     <div class="min-h-screen bg-wt-offwhite">
      <section class="relative bg-wt-oxblood py-20 lg:py-28 px-6 overflow-hidden">
       <div class="absolute inset-0 opacity-20" style="background: radial-gradient(circle at 50% 30%, rgba(201, 169, 98, 0.4), transparent 60%);">
       </div>
       <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-wt-gold to-transparent">
       </div>
       <div class="relative z-10 text-center max-w-2xl mx-auto">
        <svg class="lucide lucide-gift text-wt-gold mx-auto mb-6" fill="none" height="36" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="36" xmlns="http://www.w3.org/2000/svg">
         <rect height="4" rx="1" width="18" x="3" y="8">
         </rect>
         <path d="M12 8v13">
         </path>
         <path d="M19 12v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-7">
         </path>
         <path d="M7.5 8a2.5 2.5 0 0 1 0-5A4.8 8 0 0 1 12 8a4.8 8 0 0 1 4.5-5 2.5 2.5 0 0 1 0 5">
         </path>
        </svg>
        <p class="font-label text-xs tracking-[0.3em] uppercase text-wt-gold mb-4">
         The Art of Giving
        </p>
        <h1 class="font-heading text-4xl md:text-6xl text-wt-cream leading-tight mb-6">
         William Taylor
         <br/>
         <span class="italic text-wt-gold">
          Gift Cards
         </span>
        </h1>
        <p class="font-body text-base text-wt-cream/60 font-light max-w-lg mx-auto leading-relaxed">
         The gift of sartorial distinction. Choose any amount, select a design, and deliver luxury instantly to those who matter most.
        </p>
       </div>
      </section>
      <section class="py-16 lg:py-24 px-6">
       <div class="max-w-5xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-start">
        <div class="lg:sticky lg:top-20">
         <p class="section-subtitle mb-3">
          Your Card
         </p>
         <h2 class="font-heading text-2xl text-wt-oxblood mb-6">
          Live Preview
         </h2>
         <div class="relative w-full aspect-[1.586/1] rounded-xl overflow-hidden shadow-2xl group" style="background: linear-gradient(135deg, rgb(65, 23, 27) 0%, rgb(90, 31, 36) 40%, rgb(42, 15, 18) 100%); opacity: 1; transform: none;">
          <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-700" style="background: linear-gradient(105deg, transparent 30%, rgba(201, 169, 98, 0.15) 45%, rgba(244, 230, 191, 0.25) 50%, rgba(201, 169, 98, 0.15) 55%, transparent 70%);">
          </div>
          <div class="absolute inset-0" style="background: linear-gradient(105deg, transparent 40%, rgba(255, 255, 255, 0.05) 50%, transparent 60%);">
          </div>
          <div class="absolute inset-0 pointer-events-none" style='background-image: url("/website/images/eab6bab5d_bg.jpg"); background-size: 200px; background-repeat: repeat; opacity: 0.07;'>
          </div>
          <div class="absolute inset-2 border rounded-lg" style="border-color: rgba(201, 169, 98, 0.19);">
          </div>
          <div class="absolute top-0 left-0 right-0 p-4 lg:p-5 flex items-start justify-between">
           <img alt="William Taylor" class="h-6 lg:h-8 w-auto max-w-[140px] object-contain mix-blend-screen opacity-95" src="/website/images/7a24ade71_LOGO-3.png"/>
           <div class="w-9 h-7 lg:w-11 lg:h-8 rounded-sm relative overflow-hidden" style="background: linear-gradient(135deg, rgb(201, 169, 98), rgb(139, 114, 53), rgb(201, 169, 98));">
            <div class="absolute inset-1 border rounded-sm" style="border-color: rgba(0, 0, 0, 0.2);">
            </div>
            <div class="absolute top-1/2 left-0 right-0 h-px" style="background: rgba(0, 0, 0, 0.15);">
            </div>
            <div class="absolute top-0 bottom-0 left-1/2 w-px" style="background: rgba(0, 0, 0, 0.15);">
            </div>
           </div>
          </div>
          <div class="absolute inset-0 flex flex-col items-center justify-center">
           <p class="font-label text-[8px] lg:text-[9px] tracking-[0.3em] uppercase mb-1" style="color: rgba(201, 169, 98, 0.6);">
            Gift Card Value
           </p>
           <p class="font-heading text-2xl lg:text-3xl" style="color: rgb(244, 230, 191);">
            100,000 TZS
           </p>
          </div>
          <div class="absolute bottom-0 left-0 right-0 p-4 lg:p-5 flex items-end justify-between">
           <div>
            <p class="font-label text-[7px] lg:text-[8px] tracking-[0.2em] uppercase mb-0.5" style="color: rgba(201, 169, 98, 0.5);">
             Recipient
            </p>
            <p class="font-heading text-sm lg:text-base" style="color: rgb(244, 230, 191);">
             William Taylor
            </p>
           </div>
           <div class="text-right">
            <p class="font-label text-[7px] lg:text-[8px] tracking-[0.2em] uppercase mb-0.5" style="color: rgba(201, 169, 98, 0.5);">
             Card Code
            </p>
            <p class="font-mono text-xs lg:text-sm tracking-wider" style="color: rgb(244, 230, 191);">
             WT-GC-PREVIEW
            </p>
           </div>
          </div>
          <div class="absolute bottom-0 left-0 right-0 h-px" style="background: linear-gradient(to right, transparent, rgb(201, 169, 98), transparent);">
          </div>
         </div>
         <div class="mt-6 flex items-center gap-3 text-wt-black/40">
          <svg class="lucide lucide-sparkles text-wt-gold" fill="none" height="14" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="14" xmlns="http://www.w3.org/2000/svg">
           <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z">
           </path>
           <path d="M20 3v4">
           </path>
           <path d="M22 5h-4">
           </path>
           <path d="M4 17v2">
           </path>
           <path d="M5 18H3">
           </path>
          </svg>
          <p class="font-body text-xs font-light">
           Metallic finish · Premium design · Instant digital delivery
          </p>
         </div>
        </div>
        <form class="space-y-8">
         <div>
          <h3 class="font-heading text-xl text-wt-oxblood mb-4">
           Select an Amount
          </h3>
          <div class="grid grid-cols-3 gap-3 mb-4">
           <button class="py-3 font-label text-xs tracking-wider border transition-all border-wt-gold/30 text-wt-oxblood hover:border-wt-gold" type="button">
            TZS 50,000
           </button>
           <button class="py-3 font-label text-xs tracking-wider border transition-all bg-wt-oxblood text-wt-cream border-wt-oxblood" type="button">
            TZS 100,000
           </button>
           <button class="py-3 font-label text-xs tracking-wider border transition-all border-wt-gold/30 text-wt-oxblood hover:border-wt-gold" type="button">
            TZS 200,000
           </button>
           <button class="py-3 font-label text-xs tracking-wider border transition-all border-wt-gold/30 text-wt-oxblood hover:border-wt-gold" type="button">
            TZS 500,000
           </button>
           <button class="py-3 font-label text-xs tracking-wider border transition-all border-wt-gold/30 text-wt-oxblood hover:border-wt-gold" type="button">
            TZS 1,000,000
           </button>
           <button class="py-3 font-label text-xs tracking-wider border transition-all border-wt-gold/30 text-wt-oxblood hover:border-wt-gold" type="button">
            TZS 2,000,000
           </button>
          </div>
          <div class="relative">
           <span class="absolute left-4 top-1/2 -translate-y-1/2 font-label text-xs text-wt-black/40">
            TZS
           </span>
           <input class="w-full border border-wt-gold/30 pl-12 pr-4 py-3 font-body text-sm outline-none focus:border-wt-gold bg-white" min="10000" placeholder="Or enter custom amount" step="5000" type="number" value=""/>
          </div>
          <p class="font-body text-xs text-wt-black/40 font-light mt-2">
           Minimum: TZS 10,000 · Maximum: TZS 5,000,000
          </p>
         </div>
         <div>
          <h3 class="font-heading text-xl text-wt-oxblood mb-4 flex items-center gap-2">
           <svg class="lucide lucide-palette text-wt-gold" fill="none" height="18" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="18" xmlns="http://www.w3.org/2000/svg">
            <circle cx="13.5" cy="6.5" fill="currentColor" r=".5">
            </circle>
            <circle cx="17.5" cy="10.5" fill="currentColor" r=".5">
            </circle>
            <circle cx="8.5" cy="7.5" fill="currentColor" r=".5">
            </circle>
            <circle cx="6.5" cy="12.5" fill="currentColor" r=".5">
            </circle>
            <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z">
            </path>
           </svg>
           Choose a Design
          </h3>
          <div class="grid grid-cols-3 gap-3">
           <button class="py-4 border transition-all text-center border-wt-gold bg-wt-cream/30" type="button">
            <div class="w-full h-8 mb-2 rounded" style="background: linear-gradient(135deg, rgb(65, 23, 27), rgb(90, 31, 36));">
            </div>
            <p class="font-label text-[10px] tracking-wider uppercase text-wt-oxblood font-semibold">
             Oxblood
            </p>
            <p class="font-display text-xs italic text-wt-gold">
             Signature
            </p>
           </button>
           <button class="py-4 border transition-all text-center border-wt-gold/20 hover:border-wt-gold/50" type="button">
            <div class="w-full h-8 mb-2 rounded" style="background: linear-gradient(135deg, rgb(201, 169, 98), rgb(184, 146, 63));">
            </div>
            <p class="font-label text-[10px] tracking-wider uppercase text-wt-oxblood font-semibold">
             Gold
            </p>
            <p class="font-display text-xs italic text-wt-gold">
             Luminous
            </p>
           </button>
           <button class="py-4 border transition-all text-center border-wt-gold/20 hover:border-wt-gold/50" type="button">
            <div class="w-full h-8 mb-2 rounded" style="background: linear-gradient(135deg, rgb(26, 26, 26), rgb(42, 42, 42));">
            </div>
            <p class="font-label text-[10px] tracking-wider uppercase text-wt-oxblood font-semibold">
             Noir
            </p>
            <p class="font-display text-xs italic text-wt-gold">
             Understated
            </p>
           </button>
          </div>
         </div>
         <div>
          <h3 class="font-heading text-xl text-wt-oxblood mb-4">
           Recipient Details
          </h3>
          <div class="space-y-3">
           <div>
            <label class="font-label text-[10px] tracking-wider uppercase text-wt-gold mb-1.5 flex items-center gap-1.5">
             <svg class="lucide lucide-user" fill="none" height="11" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="11" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2">
              </path>
              <circle cx="12" cy="7" r="4">
              </circle>
             </svg>
             Recipient Name *
            </label>
            <input class="w-full border border-wt-gold/30 px-4 py-3 font-body text-sm outline-none focus:border-wt-gold bg-white" required="" type="text" value=""/>
           </div>
           <div>
            <label class="font-label text-[10px] tracking-wider uppercase text-wt-gold mb-1.5 flex items-center gap-1.5">
             <svg class="lucide lucide-mail" fill="none" height="11" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="11" xmlns="http://www.w3.org/2000/svg">
              <rect height="16" rx="2" width="20" x="2" y="4">
              </rect>
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7">
              </path>
             </svg>
             Recipient Email *
            </label>
            <input class="w-full border border-wt-gold/30 px-4 py-3 font-body text-sm outline-none focus:border-wt-gold bg-white" required="" type="email" value=""/>
           </div>
           <div>
            <label class="font-label text-[10px] tracking-wider uppercase text-wt-gold mb-1.5 flex items-center gap-1.5">
             <svg class="lucide lucide-user" fill="none" height="11" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="11" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2">
              </path>
              <circle cx="12" cy="7" r="4">
              </circle>
             </svg>
             Your Name *
            </label>
            <input class="w-full border border-wt-gold/30 px-4 py-3 font-body text-sm outline-none focus:border-wt-gold bg-white" required="" type="text" value=""/>
           </div>
           <div>
            <label class="font-label text-[10px] tracking-wider uppercase text-wt-gold mb-1.5 flex items-center gap-1.5">
             <svg class="lucide lucide-mail" fill="none" height="11" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="11" xmlns="http://www.w3.org/2000/svg">
              <rect height="16" rx="2" width="20" x="2" y="4">
              </rect>
              <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7">
              </path>
             </svg>
             Your Email *
            </label>
            <input class="w-full border border-wt-gold/30 px-4 py-3 font-body text-sm outline-none focus:border-wt-gold bg-white" required="" type="email" value=""/>
           </div>
           <div>
            <label class="font-label text-[10px] tracking-wider uppercase text-wt-gold mb-1.5 flex items-center gap-1.5">
             <svg class="lucide lucide-message-square" fill="none" height="11" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="11" xmlns="http://www.w3.org/2000/svg">
              <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z">
              </path>
             </svg>
             Personal Message
            </label>
            <textarea class="w-full border border-wt-gold/30 px-4 py-3 font-body text-sm outline-none focus:border-wt-gold bg-white resize-none" placeholder="Add a personal note..." rows="3"></textarea>
           </div>
          </div>
         </div>
         <div class="pt-2">
          <button class="w-full btn-gold py-5 text-xs flex items-center justify-center gap-2" type="submit">
           Purchase Gift Card — TZS 100,000
           <svg class="lucide lucide-chevron-right" fill="none" height="14" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="14" xmlns="http://www.w3.org/2000/svg">
            <path d="m9 18 6-6-6-6">
            </path>
           </svg>
          </button>
          <p class="font-body text-[11px] text-wt-black/40 font-light text-center mt-3">
           Digital delivery · No expiry on balances · Redeemable at checkout
          </p>
         </div>
        </form>
       </div>
      </section>
      <section class="bg-wt-oxblood py-16 px-6">
       <div class="max-w-4xl mx-auto">
        <h2 class="font-heading text-3xl text-wt-cream text-center mb-12">
         How It Works
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
         <div class="text-center" style="opacity: 1; transform: none;">
          <p class="font-heading text-4xl text-wt-gold/30 mb-3">
           01
          </p>
          <h3 class="font-heading text-xl text-wt-cream mb-2">
           Choose
          </h3>
          <p class="font-body text-sm text-wt-cream/50 font-light leading-relaxed">
           Select an amount and design that speaks to your recipient.
          </p>
         </div>
         <div class="text-center" style="opacity: 1; transform: none;">
          <p class="font-heading text-4xl text-wt-gold/30 mb-3">
           02
          </p>
          <h3 class="font-heading text-xl text-wt-cream mb-2">
           Personalize
          </h3>
          <p class="font-body text-sm text-wt-cream/50 font-light leading-relaxed">
           Add a personal message and the recipient's details.
          </p>
         </div>
         <div class="text-center" style="opacity: 1; transform: none;">
          <p class="font-heading text-4xl text-wt-gold/30 mb-3">
           03
          </p>
          <h3 class="font-heading text-xl text-wt-cream mb-2">
           Deliver
          </h3>
          <p class="font-body text-sm text-wt-cream/50 font-light leading-relaxed">
           The gift card is delivered instantly via email, ready to redeem.
          </p>
         </div>
        </div>
       </div>
      </section>
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
