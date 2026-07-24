# CMS Preview Security

`GET /preview/pages/{page}/{revision}` requires authentication, verified email, `pages.preview`, and a valid Laravel temporary signature. URLs expire after 15 minutes. The controller verifies that the selected immutable revision belongs to the page.

Responses include `X-Robots-Tag: noindex, nofollow` and `Cache-Control: private, no-store, max-age=0`. Preview routes are absent from sitemaps and public cache. The page shows a draft banner, revision number and timestamp, and no edit or mutation control.

Preview loads media in one bounded query, renders code-owned CMS preview views, escapes structured text, and renders only server-sanitized rich text. It does not read mutable Livewire form state. Guest, unverified, unauthorized, expired, invalid-signature, and cross-page revision requests are rejected.

Unauthenticated share links and public CMS rendering are deferred.
