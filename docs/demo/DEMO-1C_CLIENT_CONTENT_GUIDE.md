# DEMO-1C client content guide

## Open the Client Demo

Sign in with an authorized demo administrator account and open **Client Demo**. The banner identifies the isolated Demo Environment. Choose **Manage Website Content** for website changes or **Manage Customer Orders** for the existing Order workflow.

## Homepage and global content

Open **Homepage and global content**. This workspace controls the supported global content already used by the homepage, including navigation, announcement, brand/profile, contact details, social links, footer copy, and supported global images. The protected imported homepage body is not a visual page builder and remains static.

Save a draft, resolve any readiness messages, open the signed preview, submit the exact revision for review, and use the appropriately authorized account for approval and demo publication. Production publication is not authorized.

## Managed Pages and About

Open **Pages**, then select **About**. Edit only the typed fields and sections shown by the editor. Image controls list ready Media assets; an informative image must have meaningful effective alternative text.

Save creates a new immutable revision. It does not alter the published revision. Preview opens a temporary, signed, exact-revision view marked as a preview and excluded from indexing. Submit the revision for review, then approve it with a separately eligible account where separation of duties applies. Publishing affects only the configured demo projection.

## History, rollback, and fallback

The Page detail screen lists previous revisions. An authorized user can choose **Create new draft from this revision**. This copies the historical payload into a new draft; it never edits or republishes the historical revision directly.

Use **Unpublish** to remove the governed demo projection and restore the protected static About fallback. The global publication kill switch and emergency-disabled state remain authoritative.

## Customer Orders

Return to **Client Demo** and choose **Manage Customer Orders**. DEMO-1B Order operations remain separate from Page content administration.

## Page SEO

Page SEO management is not available in this demo checkpoint. Existing static and global metadata continues to render unchanged. Page SEO controls require a later dedicated governed implementation.

## Browser checkpoint status

The final focused browser checkpoint remains open. The 2026-08-04 run verified login, the Demo Environment banner, Pages index, About search, publication filtering, and clear filters in disposable SQLite. The remaining workflow was not reached because the isolated About fixture begins with a rich-text section while the runner expects a Media-capable typed section. Do not treat this partial run as publication approval or production readiness.
