<div data-wishlist-items data-count="{{ $wishlistCards->count() }}">
    @if($wishlistCards->isNotEmpty())
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            @foreach($wishlistCards as $card) @include('frontend.partials.product-card', ['card' => $card]) @endforeach
        </div>
    @else
       <div class="text-center py-20">
        <svg class="lucide lucide-heart mx-auto text-gray-300 mb-4" fill="none" height="40" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewbox="0 0 24 24" width="40" xmlns="http://www.w3.org/2000/svg">
         <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z">
         </path>
        </svg>
        <h2 class="font-heading text-3xl text-wt-oxblood mb-3">
         Your wishlist is empty
        </h2>
        <p class="font-body text-sm text-gray-500 font-light mb-8">
         Save pieces you love and come back when you're ready.
        </p>
        <a class="btn-gold px-10 py-4" href="{{ route('products.index') }}">
         Explore the Collection
        </a>
       </div>
    @endif
</div>
