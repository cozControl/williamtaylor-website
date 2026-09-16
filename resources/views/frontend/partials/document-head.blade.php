
  <meta charset="utf-8"/>
  <link href="https://media.base44.com/images/public/6a4d9ad469285a7e6df866f1/48af4981b_new.png" rel="icon" type="image/png"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
  <!-- SEO -->
  <title>
   {{ $catalogueSeo['title'] ?? 'William Taylor - Contemporary Menswear | Crafted for the Modern Gentleman' }}
  </title>
  <meta content="{{ $catalogueSeo['description'] ?? 'William Taylor - Luxury contemporary menswear crafted in Tanzania. Shop exclusive pieces, limited editions, and pre-order designs. Free delivery in Dar es Salaam.' }}" name="description"/>
  <meta content="William Taylor, luxury menswear, Tanzania fashion, Dar es Salaam, contemporary menswear, limited edition" name="keywords"/>
  <!-- Open Graph -->
  <meta content="{{ $catalogueSeo['title'] ?? 'William Taylor - Contemporary Menswear' }}" property="og:title"/>
  <meta content="{{ $catalogueSeo['description'] ?? 'Crafted for the Modern Gentleman. Contemporary menswear designed in Tanzania, worn worldwide.' }}" property="og:description"/>
  <meta content="https://media.base44.com/images/public/6a4d9ad469285a7e6df866f1/48af4981b_new.png" property="og:image"/>
  <meta content="website" property="og:type"/>
  @isset($catalogueSeo)
   <link rel="canonical" href="{{ $catalogueSeo['url'] }}">
   <meta property="og:url" content="{{ $catalogueSeo['url'] }}">
   <meta name="robots" content="{{ $catalogueSeo['robots'] }}">
  @endisset
  <!-- Twitter -->
  <meta content="summary_large_image" name="twitter:card"/>
  <meta content="{{ $catalogueSeo['title'] ?? 'William Taylor - Contemporary Menswear' }}" name="twitter:title"/>
  <!-- Avenir & Marion are system fonts; fallbacks are handled in CSS -->
  @if($loadImportedStorefrontRuntime ?? true)
   <script crossorigin="" src="/website/js/index-DxdnTNDA.js" type="module"></script>
  @endif
  <link crossorigin="" href="/website/css/index-X8-QjRMe.css" rel="stylesheet"/>
  <style data-storefront-grid-spacing="ecom-home-3a">
   .wt-new-arrivals-product-grid{column-gap:1rem;row-gap:2rem}
   .wt-collection-product-grid{column-gap:1rem;row-gap:2.5rem}
   @media (min-width:768px){.wt-new-arrivals-product-grid{column-gap:1.5rem}.wt-collection-product-grid{column-gap:1rem}}
   @media (min-width:1024px){.wt-collection-product-grid{column-gap:1.5rem}}
  </style>
  <script type="module">
   if (window.self === window.top && ["http:", "https:"].includes(window.location.protocol)) {
  let lastPath = "";
  function getPageNameFromPath(path) {
    const segments = path.split("/").filter(Boolean);
    return segments[0] || null;
  }
  function trackPageView() {
    const path = window.location.pathname;
    if (path === lastPath) return;
    lastPath = path;
    const pageName = getPageNameFromPath(path) || "home";
    const appId = "6a4d9ad469285a7e6df866f1";
    if (!appId) return;
    fetch(`/api/app-logs/${appId}/log-user-in-app/${pageName}`, {
      method: "POST",
    }).catch(() => {});
  }
  const originalPushState = history.pushState.bind(history);
  history.pushState = function (...args) {
    originalPushState(...args);
    trackPageView();
  };
  const originalReplaceState = history.replaceState.bind(history);
  history.replaceState = function (...args) {
    originalReplaceState(...args);
    trackPageView();
  };
  window.addEventListener("popstate", trackPageView);
  trackPageView();
}
  </script>
  <meta content="yes" name="mobile-web-app-capable"/>
  <meta content="black" name="apple-mobile-web-app-status-bar-style"/>
  <meta content="William Taylor" name="apple-mobile-web-app-title"/>

