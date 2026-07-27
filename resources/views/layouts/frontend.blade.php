<html lang="en">
 <head>
  @yield('route-bootstrap')
  @hasSection('document-head')
   @yield('document-head')
  @else
   @include('frontend.partials.document-head')
  @endif
 </head>
 <body>
  @yield('content')
  @include('frontend.partials.projected-chrome-sync')
 </body>
</html>
