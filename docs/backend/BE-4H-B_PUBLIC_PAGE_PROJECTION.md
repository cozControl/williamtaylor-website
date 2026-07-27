# BE-4H-B Public Page Projection

`/about` is the only registered public CMS Page route. `PUBLIC_PAGE_PROJECTION` defaults to false and is independent of `PUBLIC_SITE_CONTENT_PROJECTION`. The resolver loads only the English, active `about` Page and its designated `currentPublicRevision`, validates ownership, type, template, schema, section cardinality and Media readiness, then builds readonly DTOs. Any failure returns the complete static Blade fallback. Public Blade performs no Eloquent query or payload decoding.

## B.1 closure
The exact route registry remains one entry: about. Four feature-flag states and direct query, cookie, session and forged-signature isolation are validated.
