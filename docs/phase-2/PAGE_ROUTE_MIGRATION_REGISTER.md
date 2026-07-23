# Page and route migration register

Date: 2026-07-23

| FE item | Static source | Actual visible purpose | Laravel URL | Route name | View | Status |
|---|---|---|---|---|---|---|
| FE-1A | `index.html` | Branded storefront homepage | `/` | `home` | `welcome` | Fidelity verified |
| FE-2A | `html/page_2.html` | Collection landing grid with six collection cards | `/collections` | `collections.index` | `frontend.collections` | Fidelity reviewed |
| FE-2A | `html/page_3.html` | Shop All catalogue with 24 static product cards, filter trigger and ordering control | `/shop` | `products.index` | `frontend.shop` | Fidelity verified |

Pages 4-13 and their pre-existing working-tree routes/views were not migrated, refactored, or fidelity-approved by FE-2A.
## FE-2B additions

| FE item | Static source | Actual visible purpose | Laravel URL | Route name | View | Status |
|---|---|---|---|---|---|---|
| FE-2B | `html/page_4.html` | Pre-order campaign and notify presentation | `/pre-order` | `preorders.index` | `frontend.pre-order` | Fidelity reviewed |
| FE-2B | `html/page_5.html` | Limited-edition campaign/product presentation | `/limited-edition` | `limited-edition.index` | `frontend.limited-edition` | Fidelity verified |
| FE-2B | `html/page_6.html` | Gift-card amount/design/recipient/purchaser presentation | `/gift-cards` | `gift-cards.index` | `frontend.gift-cards` | Fidelity verified |




## FE-2C additions

| FE item | Static source | Actual visible purpose | Laravel URL | Route name | View | Status |
|---|---|---|---|---|---|---|
| FE-2C | `html/page_7.html` | Customer login presentation backed by the existing Fortify authentication contract | `/login` | `login` | `livewire.auth.login` | Fidelity verified |
| FE-2C | `html/page_8.html` | Empty wishlist presentation with storefront chrome | `/wishlist` | `wishlist.index` | `frontend.wishlist` | Fidelity verified; persistence deferred |

## FE-2D addition

| FE item | Static source | Actual visible purpose | Laravel URL | Route name | View | Status |
|---|---|---|---|---|---|---|
| FE-2D | `html/page_9.html` | The Taylor Oxford Shirt product detail presentation | `/products/the-taylor-oxford-shirt` | `products.taylor-oxford-shirt` | `frontend.products.taylor-oxford-shirt` | Fidelity verified; commerce behavior deferred |

Pages 10-13 remain outside FE-2D and require separately bounded FE-2E work.
## FE-2E addition

| FE item | Static source | Actual visible purpose | Laravel URL | Route name | View | Status |
|---|---|---|---|---|---|---|
| FE-2E | `html/page_10.html` | Mercerized Cotton Polo product-detail presentation | `/products/mercerized-cotton-polo` | `products.mercerized-cotton-polo` | `frontend.products.mercerized-cotton-polo` | Fidelity verified; commerce behavior deferred |

Pages 11-13 remain outside FE-2E and require separately bounded FE-2F work.
## FE-2F addition

| FE item | Static source | Actual visible purpose | Laravel URL | Route name | View | Status |
|---|---|---|---|---|---|---|
| FE-2F | `html/page_11.html` | The Dar es Salaam Linen Suit product-detail presentation | `/products/the-dar-es-salaam-linen-suit` | `products.dar-es-salaam-linen-suit` | `frontend.products.dar-es-salaam-linen-suit` | Fidelity verified; commerce behavior deferred |

Pages 12-13 remain outside FE-2F and require separately bounded FE-2G work.
## FE-2G addition

| FE item | Static source | Actual visible purpose | Laravel URL | Route name | View | Status |
|---|---|---|---|---|---|---|
| FE-2G | `html/page_12.html` | Slim Tapered Chinos product-detail presentation | `/products/slim-tapered-chinos` | `products.slim-tapered-chinos` | `frontend.products.slim-tapered-chinos` | Fidelity verified; commerce behavior deferred |

Page 13 remains outside FE-2G and requires separately bounded FE-2H work.
## FE-2H addition

| FE item | Static source | Actual visible purpose | Laravel URL | Route name | View | Status |
|---|---|---|---|---|---|---|
| FE-2H | `html/page_13.html` | The Executive Overcoat pre-order product-detail presentation | `/products/the-executive-overcoat` | `products.executive-overcoat` | `frontend.products.executive-overcoat` | Fidelity verified; reservation and commerce behavior deferred |

The imported bundle’s source-exact slug is `/product/executive-overcoat` (without “the”), as established by links in the supplied pages. FE-2H completes fidelity migration of the supplied `page_2.html` through `page_13.html` set. Any later frontend or backend phase requires separate authorization.