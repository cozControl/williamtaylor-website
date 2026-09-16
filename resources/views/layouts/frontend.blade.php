<html lang="en">
 <head>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script>
  // Retire only the imported demonstration cart key before its module initializes.
  (() => { try {
   localStorage.removeItem('wt_cart');
   const get = Storage.prototype.getItem, set = Storage.prototype.setItem;
   Storage.prototype.getItem = function(key) { return key === 'wt_cart' ? null : get.call(this,key); };
   Storage.prototype.setItem = function(key,value) { if(key !== 'wt_cart') set.call(this,key,value); };
  } catch {} })();
  </script>
  @yield('route-bootstrap')
  @hasSection('document-head')
   @yield('document-head')
  @else
   @include('frontend.partials.document-head')
  @endif
  @include('frontend.partials.header-styles')
 </head>
 <body>
  @yield('content')
  @include('frontend.partials.projected-chrome-sync')
  <script id="storefront-contact-details" type="application/json">@json($storefrontContact)</script>
  <script src="{{ asset('website/js/storefront-contact.js') }}" defer></script>
  @include('frontend.partials.shop-navigation-script')
  @include('frontend.partials.purchase-boundary')
  @include('frontend.partials.cart-shell')
  <script src="{{ asset('website/js/catalogue-wishlist.js') }}" defer></script>
 </body>
</html>
