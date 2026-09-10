@extends('layouts.frontend')
@section('content')
@include('frontend.partials.checkout-styles')
<div class="min-h-screen bg-wt-offwhite">
 <header class="wt-checkout-header"><a href="{{ route('home') }}"><img src="{{ asset('website/images/8d99836ea_LOGO-3.png') }}" alt="William Taylor"></a></header>
 <main class="wt-checkout font-body">
 <h1 class="font-heading text-3xl text-wt-oxblood">Checkout</h1>
 @if($errors->any())
 <div role="alert" class="wt-checkout-error">{{ $errors->first() }}</div>
 @endif
 @if(!$cart['is_checkout_ready'])
 <section class="wt-checkout-panel"><h2 class="font-heading text-xl">{{ $cart['is_empty'] ? 'Your cart is empty' : 'Review your bag' }}</h2><p>{{ $cart['is_empty'] ? 'Discover pieces crafted for the modern gentleman.' : 'Some items need your attention before you can place an order.' }}</p><a href="{{ route('cart.show') }}" class="btn-outline">View Your Bag</a></section>
 @else
 <form method="POST" action="{{ route('checkout.store') }}" class="wt-checkout-grid" id="wt-checkout-form">
 @csrf
 <input type="hidden" name="submission" value="{{ $submission }}">
 <div>
 @foreach(['Contact Information' => ['email' => ['Email Address','email','email'], 'phone' => ['Phone Number','tel','tel']], 'Shipping Address' => ['name' => ['Full Name','text','name'], 'address' => ['Street Address','text','street-address'], 'city' => ['City','text','address-level2'], 'region' => ['Region','text','address-level1'], 'postal' => ['Postal Code (optional)','text','postal-code']]] as $heading => $fields)
 <section class="wt-checkout-panel"><h2 class="font-heading text-xl text-wt-oxblood">{{ $heading }}</h2><div class="wt-checkout-fields">
 @foreach($fields as $field => [$label, $type, $autocomplete])
 <div @class(['wt-checkout-wide' => in_array($field, ['email','name','address'])])>
 <label for="checkout-{{ $field }}" class="font-label text-xs tracking-widest uppercase text-gray-500">{{ $label }}</label>
 <input id="checkout-{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" value="{{ old($field) }}" @required($field !== 'postal') aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" @if($errors->has($field)) aria-describedby="error-{{ $field }}" @endif>
 @error($field)
 <p class="wt-checkout-error" id="error-{{ $field }}">{{ $message }}</p>
 @enderror
 </div>
 @endforeach
 </div></section>
 @endforeach
 <section class="wt-checkout-panel"><h2 class="font-heading text-xl text-wt-oxblood">Delivery &amp; Payment</h2><p>Delivery arrangements and any delivery charges are pending confirmation. {{ config('snippe.enabled') ? 'Continue to Snippe’s secure checkout to pay for your items.' : 'No payment is collected here.' }}</p></section>
 <button type="submit" class="btn-gold wt-checkout-submit" data-idle-label="{{ config('snippe.enabled') ? 'Continue to secure payment' : 'Place Order' }}">{{ config('snippe.enabled') ? 'Continue to secure payment' : 'Place Order' }}</button>
 </div>
 <aside class="wt-checkout-panel wt-checkout-summary"><h2 class="font-heading text-xl text-wt-oxblood">Order Summary</h2>
 @foreach($cart['lines'] as $line)
 <article class="wt-checkout-row"><div><h3 class="font-heading text-wt-oxblood">{{ $line['title'] }}</h3><p class="text-xs">{{ implode(' / ', $line['options']) }}</p><p class="text-xs">{{ $line['unit_price'] }} each × {{ $line['quantity'] }}</p></div><span>{{ $line['line_total'] }}</span></article>
 @endforeach
 <div class="wt-checkout-row"><span>Merchandise subtotal</span><strong>{{ $cart['subtotal'] }}</strong></div><p class="text-xs text-gray-500">Delivery charges have not been calculated.</p><a href="{{ route('cart.show') }}" class="btn-outline">Edit Your Bag</a>
 </aside>
 </form>
 @endif
 </main>
</div>
<script>document.getElementById('wt-checkout-form')?.addEventListener('submit',function(){const button=this.querySelector('button[type="submit"]');button.disabled=true;button.textContent='Submitting…';});window.addEventListener('pageshow',()=>{const button=document.querySelector('#wt-checkout-form button[type="submit"]');if(button){button.disabled=false;button.textContent=button.dataset.idleLabel;}});</script>
@endsection
