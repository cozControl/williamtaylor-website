<style>
 /* The announcement keeps its own surface; navigation remains transparent. */
 [data-canonical-shop-header]{display:contents}
 [data-header-announcement]{position:fixed;inset:0 0 auto;z-index:50;transition:transform .24s ease}
 [data-canonical-shop-header] [hidden]{display:none!important}
 .wt-minimal-header{position:fixed;inset:var(--wt-announcement-height,0px) 0 auto;z-index:40;height:64px;background:transparent;color:#482125;transition:transform .24s ease,background-color .2s,color .2s}
 [data-smart-header="hidden-scrolled"] .wt-minimal-header{transform:translateY(calc(-100% - var(--wt-announcement-height,0px)))}
 [data-smart-header="hidden-scrolled"] [data-header-announcement]{transform:translateY(-100%)}
 [data-canonical-shop-header]:focus-within .wt-minimal-header,[data-canonical-shop-header]:focus-within [data-header-announcement]{transform:none}
 .wt-header-brand{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:clamp(160px,23vw,280px);display:flex;align-items:center;justify-content:center;min-height:44px}
 .wt-header-brand img{display:block;width:100%;height:auto;max-height:32px;object-fit:contain;filter:brightness(0)}
 .wt-minimal-header [data-header-tone="light"]{color:#fff}
 .wt-header-brand[data-header-tone="light"] img{filter:brightness(0) invert(1) drop-shadow(0 0 1px #000) drop-shadow(0 1px 1px #0009)}
 .wt-header-action[data-header-tone="light"] svg{filter:drop-shadow(0 0 1px #000) drop-shadow(0 1px 1px #0009)}
 .wt-header-utilities{position:absolute;right:clamp(16px,3.33vw,48px);top:50%;transform:translateY(-50%);display:flex;gap:4px}
 .wt-header-action{position:relative;display:flex;align-items:center;justify-content:center;width:44px;height:44px;color:inherit;background:none;border:0;cursor:pointer}
 .wt-header-action svg{width:20px;height:20px;stroke-width:1.6}
 .wt-header-action:hover{opacity:.7}
 .wt-minimal-header a:focus-visible,.wt-minimal-header button:focus-visible{outline:2px solid currentColor;outline-offset:2px}
 .wt-minimal-header .wt-cart-count-badge{background:#482125;color:#fff;top:0;right:0;min-width:16px;height:16px;font-size:10px}
 [data-header-tone="light"] .wt-cart-count-badge{background:#fff;color:#482125}
 [data-canonical-shop-header][data-scrolled] .wt-minimal-header{background:rgba(72,33,37,.92);color:#ede2c0;backdrop-filter:blur(12px)}
 [data-canonical-shop-header][data-scrolled] .wt-header-brand img{filter:brightness(0) invert(1)}
 [data-canonical-shop-header][data-scrolled] .wt-cart-count-badge{background:#ede2c0;color:#482125}
 @media(max-width:600px){
  .wt-minimal-header{height:56px}
  .wt-header-utilities{right:8px;gap:0}
  .wt-header-action{width:36px;height:44px}
  .wt-header-action svg{width:18px;height:18px}
  .wt-header-brand{width:clamp(72px,calc(100vw - 248px),160px)}
 }
 @media(prefers-reduced-motion:reduce){.wt-minimal-header,[data-header-announcement]{transition:none}}
 /* The imported toast viewport has padding even when empty. Let taps through
    its empty shell; populated notifications retain their normal interaction. */
 #root .fixed.top-0.max-h-screen.flex-col-reverse:not(:has(*)),
 #root .fixed.top-0.max-h-screen.flex-col-reverse:has(> .fixed:only-child):not(:has(> .fixed > *)){pointer-events:none}
</style>
