<style>
 .wt-hot-sale{width:100%;margin:0;padding:48px 0;background:#fff}
 .wt-hot-sale-grid{display:grid;grid-template-columns:minmax(0,1fr);column-gap:0;row-gap:1px}
 .wt-hot-sale [data-hot-sale-tile]{min-width:0;height:75vh;height:75svh;min-height:420px;max-height:800px;aspect-ratio:auto}
 .wt-hot-sale [data-hot-sale-tile]>a{display:block;width:100%;height:100%;position:relative}
 .wt-hot-sale [data-hot-sale-tile] img,.wt-hot-sale video{display:block;width:100%;height:100%;object-fit:cover;object-position:center top}
 .wt-hot-sale [data-hot-sale-tile] h3,.wt-hot-sale [data-hot-sale-tile] p{overflow-wrap:anywhere;text-shadow:0 1px 5px #0008}
 .wt-hot-sale [data-hot-sale-tile]>a>.absolute:last-child{padding:32px 24px}
 .wt-hot-sale [data-hot-sale-tile]>a:focus-visible{outline:3px solid #c4a35a;outline-offset:-6px}
 @media(min-width:1024px){
  .wt-hot-sale{padding:80px 0}
  .wt-hot-sale-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .wt-hot-sale [data-hot-sale-tile]{height:100vh;height:100svh;min-height:560px;max-height:none}
  .wt-hot-sale [data-hot-sale-tile]:nth-child(3){grid-column:1 / -1}
  .wt-hot-sale [data-hot-sale-tile]>a>.absolute:last-child{padding:48px}
 }
 @media(prefers-reduced-motion:reduce){.wt-hot-sale *{transition:none!important;transform:none!important}}
</style>
