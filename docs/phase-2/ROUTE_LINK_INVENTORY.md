# FE-2A route and link inventory

Date: 2026-07-23

| Source destination | Visible purpose | FE-2A Laravel destination | Status / future phase |
|---|---|---|---|
| `/` | Home/logo | `home` | Implemented |
| `/collections` | Collections | `collections.index` | Implemented |
| `/shop`, `/shop?sort=newest` | Shop / New Arrivals | `products.index` with source query preserved where present | Implemented as static presentation; sorting deferred |
| `/account` | Customer account | `login` where the supplied action represents sign-in | Existing authentication destination |
| `/wishlist` | Wishlist | None added by FE-2A | Persistence and guest identity deferred |
| `/cart` | Bag/cart | None added by FE-2A | Cart persistence deferred |
| `/product/...` | Product details | None added by FE-2A | Catalogue/product phase |
| `/pre-order`, `/collections/limited-edition`, `/gift-cards` | Merchandising pages | None added by FE-2A | Later bounded frontend/ecommerce phases |
| `/collections/mens-wear`, `/collections/unisex`, `/collections/accessories` | Collection cards | None added by FE-2A | Collection catalogue phase |
| `/about`, `/contact`, `/membership`, `/track-order`, `/shipping`, `/returns`, `/size-guide`, `/faq`, `/privacy`, `/terms`, `/admin` | Footer/service/legal links | None added by FE-2A | Content, customer-service, legal or administration phases |

The repository already contained routes for several later supplied pages before FE-2A. FE-2A did not add, remove, or expand those routes and does not treat them as FE-2A-approved migrations.

## FE-2B additions

| Source destination | Laravel destination | Status |
|---|---|---|
| `/pre-order` | `preorders.index` | Public presentation implemented; order/reservation/enquiry deferred |
| `/collections/limited-edition` | `limited-edition.index` at `/limited-edition` | Public presentation implemented; stock/campaign rules deferred |
| `/gift-cards` | `gift-cards.index` | Public presentation implemented; purchase/issuance/redemption deferred |

Home, Collections, Shop and login links use existing named routes where equivalent. Product, wishlist, bag, checkout, service and legal destinations remain unresolved; none were replaced with `#` or invented.
