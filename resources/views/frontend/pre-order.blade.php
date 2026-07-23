@extends('layouts.frontend')

@section('content')
  <div id="root">
   <div class="min-h-screen flex flex-col bg-wt-offwhite">
    <header class="fixed top-0 left-0 right-0 z-40">
     @include('frontend.partials.announcement')
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
         Pre-Order
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
      <div class="bg-wt-oxblood py-16 px-6 text-center">
       <p class="font-label text-xs tracking-[0.3em] uppercase text-wt-gold mb-3">
        Exclusive Access
       </p>
       <h1 class="font-heading text-5xl text-wt-cream">
        Pre-Order
       </h1>
       <p class="font-body text-sm text-wt-cream/60 font-light mt-3 max-w-md mx-auto">
        Reserve exclusive pieces before they launch. 50% deposit secures your order.
       </p>
      </div>
      <div class="max-w-screen-xl mx-auto px-6 lg:px-12 py-16 space-y-12">
       <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 bg-white overflow-hidden shadow-sm" style="opacity: 1; transform: none;">
        <div class="aspect-[4/3] lg:aspect-auto overflow-hidden">
         <img alt="The Executive Overcoat" class="w-full h-full object-cover object-top hover:scale-105 transition-transform duration-700" src="/website/images/81f56a965_image.jpg"/>
        </div>
        <div class="p-10 lg:p-14 flex flex-col justify-center">
         <span class="bg-wt-gold text-wt-oxblood font-label text-[10px] tracking-widest px-3 py-1 self-start mb-6">
          PRE-ORDER
         </span>
         <h2 class="font-heading text-3xl text-wt-oxblood mb-3">
          The Executive Overcoat
         </h2>
         <p class="font-body text-sm text-gray-500 font-light mb-4">
          A structural masterpiece. Collarless overcoat with suede shoulder inserts and a belted waist.
         </p>
         <p class="font-heading text-2xl text-wt-oxblood mb-3">
          TZS 890,000
         </p>
         <p class="font-label text-xs tracking-wider text-wt-gold uppercase mb-6">
          Estimated Delivery: 2026-08-15
         </p>
         <div class="flex gap-2">
          <div class="text-center">
           <div class="bg-wt-gold text-wt-oxblood font-label font-bold text-lg w-12 h-12 flex items-center justify-center">
            22
           </div>
           <p class="font-label text-[9px] tracking-wider uppercase text-gray-500 mt-1">
            Days
           </p>
          </div>
          <div class="text-center">
           <div class="bg-wt-gold text-wt-oxblood font-label font-bold text-lg w-12 h-12 flex items-center justify-center">
            19
           </div>
           <p class="font-label text-[9px] tracking-wider uppercase text-gray-500 mt-1">
            Hrs
           </p>
          </div>
          <div class="text-center">
           <div class="bg-wt-gold text-wt-oxblood font-label font-bold text-lg w-12 h-12 flex items-center justify-center">
            15
           </div>
           <p class="font-label text-[9px] tracking-wider uppercase text-gray-500 mt-1">
            Min
           </p>
          </div>
          <div class="text-center">
           <div class="bg-wt-gold text-wt-oxblood font-label font-bold text-lg w-12 h-12 flex items-center justify-center">
            39
           </div>
           <p class="font-label text-[9px] tracking-wider uppercase text-gray-500 mt-1">
            Sec
           </p>
          </div>
         </div>
         <div class="flex gap-4 mt-8">
          <a class="btn-primary px-8 py-4 text-xs" href="{{ route('products.executive-overcoat') }}">
           Reserve Your Piece
          </a>
          <a class="btn-outline px-8 py-4 text-xs" href="{{ route('products.executive-overcoat') }}">
           Learn More
          </a>
         </div>
         <p class="font-label text-xs text-gray-400 mt-4 tracking-wider">
          50% deposit required to reserve. Balance due at shipping.
         </p>
        </div>
       </div>
       <div class="grid grid-cols-1 lg:grid-cols-2 gap-0 bg-white overflow-hidden shadow-sm" style="opacity: 1; transform: none;">
        <div class="aspect-[4/3] lg:aspect-auto overflow-hidden">
         <img alt="Summer Linen Trousers" class="w-full h-full object-cover object-top hover:scale-105 transition-transform duration-700" src="/website/images/601f1442e_image.jpg"/>
        </div>
        <div class="p-10 lg:p-14 flex flex-col justify-center">
         <span class="bg-wt-gold text-wt-oxblood font-label text-[10px] tracking-widest px-3 py-1 self-start mb-6">
          PRE-ORDER
         </span>
         <h2 class="font-heading text-3xl text-wt-oxblood mb-3">
          Summer Linen Trousers
         </h2>
         <p class="font-body text-sm text-gray-500 font-light mb-4">
          Wide-leg linen trousers in natural stripe. Summer 2026 pre-order.
         </p>
         <p class="font-heading text-2xl text-wt-oxblood mb-3">
          TZS 295,000
         </p>
         <p class="font-label text-xs tracking-wider text-wt-gold uppercase mb-6">
          Estimated Delivery: 2026-08-01
         </p>
         <div class="flex gap-2">
          <div class="text-center">
           <div class="bg-wt-gold text-wt-oxblood font-label font-bold text-lg w-12 h-12 flex items-center justify-center">
            08
           </div>
           <p class="font-label text-[9px] tracking-wider uppercase text-gray-500 mt-1">
            Days
           </p>
          </div>
          <div class="text-center">
           <div class="bg-wt-gold text-wt-oxblood font-label font-bold text-lg w-12 h-12 flex items-center justify-center">
            19
           </div>
           <p class="font-label text-[9px] tracking-wider uppercase text-gray-500 mt-1">
            Hrs
           </p>
          </div>
          <div class="text-center">
           <div class="bg-wt-gold text-wt-oxblood font-label font-bold text-lg w-12 h-12 flex items-center justify-center">
            15
           </div>
           <p class="font-label text-[9px] tracking-wider uppercase text-gray-500 mt-1">
            Min
           </p>
          </div>
          <div class="text-center">
           <div class="bg-wt-gold text-wt-oxblood font-label font-bold text-lg w-12 h-12 flex items-center justify-center">
            39
           </div>
           <p class="font-label text-[9px] tracking-wider uppercase text-gray-500 mt-1">
            Sec
           </p>
          </div>
         </div>
         <div class="flex gap-4 mt-8">
          <a class="btn-primary px-8 py-4 text-xs" href="/product/summer-linen-trousers">
           Reserve Your Piece
          </a>
          <a class="btn-outline px-8 py-4 text-xs" href="/product/summer-linen-trousers">
           Learn More
          </a>
         </div>
         <p class="font-label text-xs text-gray-400 mt-4 tracking-wider">
          50% deposit required to reserve. Balance due at shipping.
         </p>
        </div>
       </div>
       <div class="bg-wt-oxblood p-12 text-center">
        <p class="font-label text-xs tracking-[0.3em] uppercase text-wt-gold mb-3">
         Stay Ahead
        </p>
        <h3 class="font-heading text-3xl text-wt-cream mb-3">
         Never Miss a Pre-Order
        </h3>
        <p class="font-body text-sm text-wt-cream/60 font-light mb-8 max-w-sm mx-auto">
         Be the first to know when new pre-order pieces are announced.
        </p>
        <form class="flex gap-0 max-w-sm mx-auto">
         <input class="flex-1 bg-white/10 border border-wt-gold/40 text-wt-cream placeholder-wt-cream/40 px-4 py-3 font-body text-sm outline-none" placeholder="Your email address" type="email"/>
         <button class="btn-gold px-6 py-3 text-xs" type="submit">
          Notify Me
         </button>
        </form>
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
