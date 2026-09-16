<style>
    [data-catalogue-listing] [hidden]{display:none!important}
    [data-catalogue-listing] a,[data-catalogue-listing] h1{overflow-wrap:anywhere}
    [data-catalogue-listing] select{max-width:100%;width:100%}
    [data-catalogue-sort-form]{min-width:0}
    .wt-catalogue-size{display:inline-flex;align-items:center;justify-content:center;min-width:2.5rem;padding:0 .4rem}
    .wt-catalogue-filter-content{position:sticky;top:7rem;max-height:calc(100dvh - 8rem);overflow-y:auto;padding-right:.5rem}
    @media(max-width:1023px){
        .wt-catalogue-filters{position:fixed;inset:0 auto 0 0;z-index:71;width:20rem;max-width:90vw;background:#faf7f2;padding:1.5rem}
        .wt-catalogue-filter-content{position:static;max-height:100%;padding-bottom:1.5rem}
        [data-catalogue-filter-backdrop]:not([hidden]){display:block;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:70}
    }
</style>
