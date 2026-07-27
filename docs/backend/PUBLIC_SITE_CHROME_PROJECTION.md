# Public Site Chrome Projection

`ResolvePublicSiteChrome` is a request-scoped singleton injected by a `frontend.*` view composer. It resolves `primary_navigation`, `footer_navigation`, `announcement` and `site_profile` independently from `current_public_revision_id`. Blade receives readonly view models and never reads Eloquent or raw revision payloads.

Desktop and mobile navigation use the same revision and honor visibility. The projected mobile panel provides labelled controls, `aria-expanded`, Escape close, focus trapping and focus return. Footer groups remain code-owned. Announcement selection uses UTC effective windows and fails safely if more than one is effective.

Each invalid surface falls back to its complete static counterpart. The homepage and all other page bodies are outside this projection.

## Page independence

`PUBLIC_PAGE_PROJECTION` controls only `/about` and is independent of this global Site Content flag.
