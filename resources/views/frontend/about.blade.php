@extends('layouts.frontend')
@section('document-head')
 @if($publicPage)
  @include('frontend.partials.about-document-head')
 @else
  @include('frontend.partials.document-head')
 @endif
@endsection
@section('content')
<div id="root"><div class="min-h-screen flex flex-col bg-wt-offwhite">
@include('frontend.partials.header')
<main data-about-region="main" class="flex-1 pb-16 lg:pb-0" id="main-content">
@if($publicPage)
 @include('frontend.about-projected', ['page' => $publicPage])
@else
 @include('frontend.about-static')
@endif
</main>
<div data-about-region="footer">
@include('frontend.partials.footer')
</div>
@include('frontend.partials.mobile-bottom-navigation')
@include('frontend.partials.whatsapp-action')
</div></div>
@endsection