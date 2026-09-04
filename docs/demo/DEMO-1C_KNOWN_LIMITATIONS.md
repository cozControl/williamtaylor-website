# DEMO-1C known limitations

- The final focused browser checkpoint remains incomplete. Run `demo1c-20260804045343` reached the About editor after passing login, banner, Pages index, search, filter, and clear-filter checks, but the isolated fixture began with a rich-text section while the runner expected a typed `Heading` and ready-Media role. The single permitted rerun, `demo1c-20260804045609`, stopped during fixture setup because an attempted correction correctly triggered the immutable-revision guard. The invalid fixture change was removed; a fresh authorization is required after an immutable-safe fixture correction.

- Only homepage and Page fields already supported by the repository are editable.
- The imported homepage body remains protected static; global homepage chrome is edited through Site Content.
- About is the only governed public Page projection.
- There is no arbitrary visual page builder or unrestricted HTML editor.
- Product, Collection, and Campaign content editors are not included.
- Authoritative Pricing and Inventory are not included.
- Public checkout, live payments, and a customer self-service dashboard are not included.
- Production publication is not authorized.
- The existing Base44 public-settings failure may remain in the protected imported storefront as previously documented.
- Page-owned SEO management is not implemented and is formally deferred to a dedicated governed implementation.
