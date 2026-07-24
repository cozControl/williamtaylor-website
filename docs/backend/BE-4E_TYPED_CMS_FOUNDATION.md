# BE-4E Typed CMS Foundation

BE-4E introduces draft-only typed pages without changing the public storefront.

The Content domain owns page identity, code-owned page types and templates, validated section payloads, immutable revisions, archive/restore, and concurrency checks. The existing Media domain continues to own media truth. The existing Audit and Identity domains continue to own evidence and access.

Only English (`en`) is currently accepted. Draft slugs are normalized lowercase hyphenated values, unique per locale, and reject reserved paths, URLs, query strings, fragments, duplicate separators, and edge hyphens. Draft slug changes do not create redirects.

There is no published state, public CMS renderer, public CMS route, review, approval, schedule, navigation management, SEO execution, catalogue, commerce, localization UI, or AI capability.

## Access and operations

Exactly six page permissions exist: `pages.view`, `pages.create`, `pages.edit`, `pages.preview`, `pages.archive`, and `pages.restore`. CMS Manager receives the six permissions through the registered bundle. Existing assignments are preserved; no role is assigned automatically.

Existing environments must preview and then apply the foundation alignment command. Registry changes are transactional, idempotent, and audited as `content.permission-registry.aligned`.

Cloudinary remains behind the Media provider contract. The non-production Cloudinary smoke test remains a deployment prerequisite.
