<div class="wt-cart-composition" data-checkout-ready="{{ $cart['is_checkout_ready'] ? 'true' : 'false' }}">
 @if($cart['is_empty'])
  <div class="wt-cart-empty text-center">
   <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M3 6h18v15H3zM3 6l3-4h12l3 4M8 9a4 4 0 0 0 8 0"/></svg>
   <h3 class="font-heading text-xl text-wt-oxblood mb-2">Your cart is empty</h3>
   <p class="font-body text-sm text-gray-500 mb-6 font-light">Discover pieces crafted for the modern gentleman.</p>
   <a href="{{ route('products.index') }}" class="btn-primary">Explore the Collection</a>
  </div>
 @else
  <div class="wt-cart-lines">
   @foreach($cart['lines'] as $line)
    <article class="wt-cart-line" data-cart-line="{{ $line['variant_id'] }}">
     <div class="wt-cart-image">
      @if($line['image'])<img src="{{ $line['image']['url'] }}" alt="{{ $line['image']['alt'] }}"/>@endif
     </div>
     <div class="wt-cart-identity">
      <div class="wt-cart-line-heading">
       <h3 class="font-heading text-sm text-wt-oxblood">
        @if($line['url'])<a href="{{ $line['url'] }}">{{ $line['title'] }}</a>@else {{ $line['title'] }} @endif
       </h3>
       <form method="POST" action="{{ route('cart.destroy', $line['variant_id']) }}" data-cart-form>
        @csrf @method('DELETE')
        <button type="submit" class="wt-cart-remove" aria-label="Remove {{ $line['title'] }}">×</button>
       </form>
      </div>
      <p class="font-label text-xs text-gray-500 uppercase">{{ implode(' / ', $line['options']) }}</p>
      <p class="font-label text-xs text-gray-500">{{ $line['unit_price'] ?? 'Price unavailable' }} each</p>
      <div class="wt-cart-line-bottom">
       <form method="POST" action="{{ route('cart.update', $line['variant_id']) }}" data-cart-form class="wt-cart-quantity">
        @csrf @method('PATCH')
        <button name="quantity" value="{{ $line['quantity'] - 1 }}" @disabled($line['quantity'] <= 1) aria-label="Decrease quantity of {{ $line['title'] }}">−</button>
        <span aria-label="Quantity {{ $line['quantity'] }}">{{ $line['quantity'] }}</span>
        <button name="quantity" value="{{ $line['quantity'] + 1 }}" aria-label="Increase quantity of {{ $line['title'] }}">+</button>
       </form>
       <span class="font-label text-xs font-semibold text-wt-oxblood">{{ $line['line_total'] ?? 'Unavailable' }}</span>
      </div>
      @if($line['issue'])<p class="wt-cart-issue" role="status">{{ $line['issue'] }}</p>@endif
     </div>
    </article>
   @endforeach
  </div>
  <div class="wt-cart-summary">
   <h2 class="font-heading text-xl text-wt-oxblood wt-cart-page-only">Order Summary</h2>
   <div class="wt-cart-subtotal"><span class="font-label text-xs uppercase tracking-widest text-gray-500">Subtotal</span><span class="font-heading text-lg text-wt-oxblood">{{ $cart['subtotal'] }}</span></div>
   @if($cart['is_checkout_ready'])
   <a href="{{ route('checkout.show') }}" class="btn-gold wt-cart-checkout">Proceed to Checkout</a>
   @else
   <p class="font-body text-xs text-gray-500">Review the items needing attention before checkout.</p>
   <button disabled class="btn-gold wt-cart-checkout" aria-disabled="true">Proceed to Checkout</button>
   @endif
   <a href="{{ route('cart.show') }}" class="btn-outline wt-cart-full">View Full Cart</a>
   <a href="{{ route('products.index') }}" class="btn-outline wt-cart-page-only">Continue Shopping</a>
  </div>
 @endif
</div>
