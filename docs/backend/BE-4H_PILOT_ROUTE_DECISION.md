# BE-4H Pilot Route Decision

Date: 2026-07-25
Status: Blocked at the mandatory selection gate

## Decision

No existing migrated public route currently satisfies the authorized BE-4H pilot criteria. Public projection implementation has therefore not started.

## Route inventory

| Existing route | Current purpose | Selection result |
|---|---|---|
| `/` | Homepage | Explicitly excluded |
| `/collections` | Collection discovery | Explicitly excluded |
| `/shop` | Product catalogue presentation | Requires catalogue truth |
| `/pre-order` | Product pre-order campaign | Explicitly excluded and commerce-oriented |
| `/limited-edition` | Product collection and scarcity presentation | Requires catalogue truth |
| `/gift-cards` | Stored-value product purchase presentation | Requires commerce truth |
| `/wishlist` | Customer wishlist empty state | Account/commerce semantics; not a standard content Page |
| `/products/the-taylor-oxford-shirt` | Product detail | Product-driven and explicitly ineligible |
| `/products/mercerized-cotton-polo` | Product detail | Product-driven and explicitly ineligible |
| `/products/the-dar-es-salaam-linen-suit` | Product detail | Product-driven and explicitly ineligible |
| `/products/slim-tapered-chinos` | Product detail | Product-driven and explicitly ineligible |
| `/products/the-executive-overcoat` | Product detail | Product-driven and explicitly ineligible |
| `/login` | Fortify customer authentication | Authentication UI, not CMS Page content |

The repository does not currently expose an About, Contact, Story, or comparable informational route. Static template links mention some informational destinations, but those pages have not been migrated and BE-4H prohibits adding a new public URL.

## Required decision

BE-4H can proceed only after one of these is separately authorized:

1. Migrate an existing supplied informational template page in a bounded frontend phase, then enroll that existing route as the pilot.
2. Amend BE-4H to authorize a specific existing route despite its catalogue, commerce, account, or authentication semantics, together with the exact content-model boundary.
3. Amend BE-4H to authorize one specific new informational public route and its static fidelity baseline.

Selecting Gift Cards, Wishlist, Login, Shop, Limited Edition, or a product route without that amendment would silently violate the pilot selection gate and the hard exclusions.

## Scope confirmation

No Public Projection domain, feature flag, public route, storefront integration, cache, migration, permission, or later-domain implementation was started. Protected storefront assets and the guarded `willy` database were not modified by this decision review. BE-4I was not started.
