@extends('layouts.frontend')
@section('content')
<div id="root"><div class="min-h-screen flex flex-col bg-wt-offwhite">
 @include('frontend.partials.header')
 <main class="flex-1 pt-32 pb-16" data-all-collections>
  <section class="py-12 lg:py-20 bg-white">
   <div class="max-w-screen-xl mx-auto px-4 lg:px-12">
    <div class="text-center mb-10 lg:mb-14"><p class="section-subtitle mb-2 text-wt-gold">Explore</p><h1 class="section-title text-wt-oxblood">All Collections</h1></div>
    <div class="wt-all-collections-grid">
     @forelse($collections as $collection)
      @include('frontend.partials.collection-card')
     @empty
      <p class="font-body text-gray-600">Collections will appear here when available.</p>
     @endforelse
    </div>
   </div>
  </section>
 </main>
 @include('frontend.partials.footer')
</div></div>
<style>.wt-all-collections-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:16px}@media(min-width:768px){.wt-all-collections-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}</style>
@endsection
