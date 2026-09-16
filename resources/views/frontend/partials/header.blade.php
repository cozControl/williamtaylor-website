@php
 $headerBrand = $publicSiteChrome?->profile?->brandName ?: 'William Taylor';
 $headerCartCount = app(\App\Domain\Cart\CartService::class)->viewSnapshot()['item_count'];
@endphp
<header data-canonical-shop-header data-smart-header="top" class="fixed top-0 left-0 right-0 z-40">
 <div data-header-announcement>@include('frontend.partials.announcement')</div>
 <nav class="wt-minimal-header" aria-label="Storefront utilities">
  <a class="wt-header-brand" href="{{ route('home') }}" aria-label="{{ $headerBrand }} home">
   <img alt="{{ $headerBrand }}" src="{{ $publicSiteChrome?->profile?->headerLogoUrl ?: '/website/images/8d99836ea_LOGO-3.png' }}"/>
  </a>
  <div class="wt-header-utilities">
   <a class="wt-header-action" href="{{ route('search') }}" aria-label="Search"><svg aria-hidden="true" class="lucide lucide-search" fill="none" height="20" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
          <circle cx="11" cy="11" r="8">
          </circle>
          <path d="m21 21-4.3-4.3">
          </path>
         </svg></a>
   <button type="button" data-cart-open aria-label="Open cart, {{ $headerCartCount }} items" aria-haspopup="dialog" class="wt-header-action">
    <svg aria-hidden="true" class="lucide lucide-shopping-bag" fill="none" height="20" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
          <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z">
          </path>
          <path d="M3 6h18">
          </path>
          <path d="M16 10a4 4 0 0 1-8 0">
          </path>
         </svg>
    <span data-cart-count data-cart-badge class="wt-cart-count-badge" @if($headerCartCount === 0) hidden @endif>{{ $headerCartCount }}</span>
   </button>
   <a class="wt-header-action" href="{{ auth()->check() ? route('profile.edit') : route('login') }}" aria-label="{{ auth()->check() ? 'My account' : 'Sign in' }}"><svg aria-hidden="true" class="lucide lucide-user" fill="none" height="20" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg">
          <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2">
          </path>
          <circle cx="12" cy="7" r="4">
          </circle>
         </svg></a>
  </div>
 </nav>
</header>
