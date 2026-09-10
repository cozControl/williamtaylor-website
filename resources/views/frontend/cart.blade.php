@extends('layouts.frontend')
@section('content')
<div id="root" class="min-h-screen bg-wt-offwhite">
 @include('frontend.partials.header')
 <main class="wt-cart-page">
  <div class="wt-cart-page-banner"><h1 class="font-heading text-4xl text-wt-cream">Your Selection</h1></div>
  <div class="wt-cart-page-inner">
  @if(session('cart_message'))<p role="status">{{ session('cart_message') }}</p>@endif
  @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
  <div data-cart-content>@include('frontend.partials.cart-content')</div>
  </div>
 </main>
 @include('frontend.partials.footer')
</div>
@endsection
