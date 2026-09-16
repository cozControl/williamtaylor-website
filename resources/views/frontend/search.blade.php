@extends('layouts.frontend')
@section('content')
<div id="root" class="min-h-screen bg-wt-offwhite">
 @include('frontend.partials.header')
 <main class="max-w-screen-xl mx-auto px-4 lg:px-12 pb-16" style="padding-top:128px;min-height:60vh">
  <h1 class="section-title text-wt-oxblood mb-8">Search</h1>
  <form action="{{ route('search') }}" method="get" role="search" class="mb-10">
   <label for="storefront-search" class="block font-label text-sm mb-2">Search products</label>
   <div style="display:flex;gap:12px;max-width:640px">
    <input id="storefront-search" name="q" type="search" value="{{ $query }}" maxlength="100" placeholder="Product name" class="border border-wt-oxblood px-4 py-3" style="min-width:0;flex:1"/>
    <button class="bg-wt-oxblood text-wt-cream px-4 py-3" type="submit">Search</button>
   </div>
   @error('q')<p role="alert">{{ $message }}</p>@enderror
  </form>
  @if($query !== '')
   <p role="status" class="font-body mb-8">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('result', $products->total()) }} for “{{ $query }}”</p>
   <div class="wt-collection-product-grid grid grid-cols-1 min-[480px]:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
    @forelse($productCards as $card)
     @include('frontend.partials.product-card', ['card' => $card])
    @empty
     <p class="font-body">No products found. Try another product name.</p>
    @endforelse
   </div>
   <div class="mt-8">{{ $products->links() }}</div>
  @endif
 </main>
 @include('frontend.partials.footer')
</div>
@endsection
