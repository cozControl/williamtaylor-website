# FE-2A deferred behavior register

Date: 2026-07-23

| Visible control | Page | Current supplied-template behavior | Required eventual backend behavior | Expected phase | Implications |
|---|---|---|---|---|---|
| Desktop Shop trigger | Both | Present; no visible panel change detected | Catalogue navigation/menu data | Catalogue/CMS | Publication rules |
| Mobile menu trigger | Both | Present; no visible panel change detected | Accessible responsive menu if approved/source exists | Frontend follow-up | Accessibility |
| Search controls | Both | Present; no visible state change detected | Search UI, index/query service and permissions | Search phase | Query privacy/analytics |
| Account action | Both | Static navigation | Customer authentication/profile | Customer accounts | Personal data and authorization |
| Wishlist link/buttons | Both; 24 buttons on Shop | Static navigation or visual control only | Anonymous/authenticated persistence and merge | Wishlist phase | Identity, retention and consent |
| Bag control | Both | Present; no visible dialog change detected | Anonymous/authenticated cart and pricing | Cart phase | Commerce integrity |
| Collection cards | Collections | Static navigation | Published collection records and product assignment | Catalogue phase | Publication authorization |
| Product cards | Shop | Static navigation; 24 literal cards | Products, generic attributes/values/variants, pricing/media/publication | Catalogue phase | No size/colour assumptions |
| Filters | Shop | One visible trigger; no backend filtering | Attribute/price/availability query contract | Catalogue/search phase | Avoid leaking unpublished inventory |
| Ordering | Shop | One visible control; no backend sorting | Validated sort keys and stable pagination | Catalogue/search phase | Input validation |
| Newsletter form | Both | Visible form only | Consent capture, list management and internal email delivery | Communications | Tanzania privacy; 30-day operational retention where applicable |
| Footer service/legal links | Both | Static navigation; many isolated routes 404 | Approved content pages | CMS/legal phase | Legal accuracy and publishing permissions |
| WhatsApp action | Both | External static link | No backend required unless analytics/CRM later approved | Optional integrations | Third-party disclosure |
| Currency toggle | Both | Visual template control | TZS/USD price presentation and conversion policy | Pricing phase | Financial accuracy |
| Legacy Base44 calls | Both | Four same-origin API calls return 404 | Remove/replace only in separately approved bundle migration | Frontend platform phase | Console noise; do not mask |

No deferred behavior was implemented by FE-2A.

## FE-2B additions

| Visible control/claim | Page | Classification and deferral |
|---|---|---|
| Notify Me, 50% deposit, dates, countdowns, Reserve/Learn links | Pre-Order | Static/client-side campaign presentation; enquiry/order/payment/reservation/inventory/notifications deferred |
| Edition badges and scarcity copy | Limited Edition | Static campaign claims; stock limits, variants, release windows, purchase limits and status deferred |
| Amount/design selectors, recipient/purchaser/message and Purchase action | Gift Cards | Client-side visual/deferred Laravel backend; payment, issuance, value ledger, code, delivery and redemption deferred |
| Newsletter | All | Static visual form; consent, internal email and retention deferred |
| Legacy Base44 requests | All | Inherited unavailable API/log/analytics behavior; bundle replacement remains out of scope |
