# ECOM-HOME-10 — Client Stories

## 1. Status

**ECOM-HOME-10 IMPLEMENTATION READY FOR GENERAL INSPECTION**

Implementation and focused checkpoint validation are complete. Physical browser acceptance remains paused: the single browser discovery attempt returned no available browsers. No full audit was run.

## 2. Protected composition

Inspected `resources/views/welcome.blade.php` and the protected `rhe` data / `nhe` renderer in `public/website/js/index-DxdnTNDA.js`. The protected file was not modified. `rhe` occurs exactly twice: its three-record declaration and this section's rendering reference.

- Eyebrow: **Client Stories**. Heading: **What They Say**. No introduction, actions, dates, Product references or ratings.
- Three white quote cards on a cream section; centered heading and 29px brand medallion containing the 20px existing icon.
- One column below 768px; three columns at 768px and above, including 1024px and desktop. The supplied grid gap is 32px. Cards use 24px padding, increasing to 32px at 1024px. Section padding is 48px, increasing to 80px at 1024px.
- Decorative gold quotation mark, light 14px quote text, uppercase client name, location subtitle, and 40px circular portrait with a gold border and top-aligned crop.
- Static identities: **James M.**, Dar es Salaam, Tanzania, `b9c0b1bdf_image.jpg`; **David K.**, Nairobi, Kenya, `db23eed31_image.jpg`; **Marcus A.**, Cape Town, South Africa, `ee86da3ae_image.jpg`. Images remain under `/website/images/` in unmanaged mode.
- James's quote describes the Dar es Salaam Linen Suit; David's describes craftsmanship and accessible luxury; Marcus's describes Tanzanian identity and international quality. The original complete wording remains unchanged in the unmanaged template.
- Entrance motion: opacity 0→1 and translateY 30px→0, 600ms, 150ms stagger, once when entering view. There is no carousel, autoplay, slide navigation, swipe interaction or rating display.
- The section follows Women's Handbags and precedes Follow the Journey.

## 3–7. UI references, existing architecture and ownership

Accepted UI references inspected: Homepage workspace, dedicated Summer Edit editor, Women's Handbags editor and shared Media picker/field components. The existing Pages edit wrapper was also inspected as the available content-editor reference. There was no testimonial-specific editor.

Searched application domains, routes, migrations and Admin views for testimonials, Client Stories, customer reviews and feedback. Existing page-review actions represent publishing workflow; Campaign claim review is unrelated. Neither provides an editorial testimonial entity. No suitable canonical testimonial/customer-review system existed.

| Template element | Owner | Required implementation |
| --- | --- | --- |
| Section eyebrow and heading | HomepageHero | Three typed fields including managed flag |
| Fixed three quotes, attribution and visibility | Homepage-owned story positions | `HomepageClientStory` child rows keyed uniquely by Homepage and position |
| Portrait | Canonical MediaAsset through MediaUsage | Story-owned `portrait` role |
| Position order | Homepage placement | Positions 1–3 only |
| Decorative medallion and quote mark | Supplied presentation | Preserve existing assets and styling |

The bounded Homepage-owned alternative is intentional. The protected implementation is a fixed local three-item array used only by this Homepage section, with no identity route, cross-placement usage or reusable-library workflow. A reusable library and independent selector would add lifecycle and navigation that this composition does not demonstrate. Three typed child rows avoid numbered quote/name columns and arbitrary repeater JSON. The model is internal to the Homepage domain, not a competing general ClientStory/Review library.

No Customer, login, purchase verification, Product Review, moderation, rating engine or review-submission functionality was introduced.

## 8–12. Capacity, fields, Media and rating semantics

Exactly three positions are accepted. The controller rejects extra or missing positions and unrecognized story keys; the presenter reads positions 1–3 only. A database unique key prevents duplicate Homepage/position rows.

Each story owns display name (120 characters), location (160), quote (1,000, plain text), visibility, position and timestamps. Homepage owns managed flag, eyebrow and heading. No supporting-copy, rating, date, account or Product fields were added because the template has none.

Portraits use the existing paginated Media picker and MediaUsage. A publicly rendered portrait must be a confirmed, ready, unarchived image. Effective alt is contextual override, then MediaAsset default. It is never inferred from filename or automatically copied from the client name. The override label explains that it is optional only when default alt exists, and missing-alt validation is attached to the alt field. Decorative quotation marks and the medallion are explicitly hidden from assistive technology.

There are no stars or ratings in the original, so no rating values or claims were added. No original identities or quotes were seeded into managed production data.

## 13–19. Admin workflow and workspace

No independent library or sidebar destination was added. The dedicated GET/PUT `/admin/homepage/client-stories` uses existing settings view/manage permissions and Admin access middleware.

The editor has a saved-state panel, section-copy panel and three numbered story panels. Each panel shows saved visibility/readiness, a concise attention reason where necessary, a Show this story checkbox, name/location fields, quote textarea, portrait picker and conditional alt override. Hiding a position removes it from public output while preserving its editable content. No raw IDs or unlimited list appear.

Ordering is the explicit numbered panel order. Entity duplicate selection is not applicable: these are individually owned content positions, not repeated references to a library. Staff edit each position directly.

Workspace card ten is **Client Stories**, immediately after Women's Handbags. It reports ready visible count, missing readiness or no visible stories. Later Homepage sections remain unexposed.

Shared UI: `x-admin.layout`, `flash`, `field`, `media-picker`, `form-actions`, `homepage-section-summary`, `admin-panel`, `admin-section-heading`, `admin-form-grid`, status labels and choice rows. View homepage uses the established action; Back is left and Save is right. No new UI framework or visual pattern was introduced.

Saving validates plain text and portrait readiness, preserves invalid input, checks the existing Homepage optimistic lock, atomically saves the three positions and MediaUsage records, and records `homepage.client_stories.updated`. The forward migration `2026_09_09_030000_add_homepage_client_stories.php` was applied alone.

## 20–22. Managed renderer and interactions

`HomepageClientStoriesPresenter` supplies the new `frontend.partials.homepage-client-stories` partial. Unmanaged mode retains the exact original section. Managed mode owns the entire section and the inert `homepage-client-stories-projection` template.

`synchronizeClientStories` extends the existing bounded Homepage synchronizer. It locates the original section by **What They Say**, replaces it with the inert managed clone, and stops when the managed marker already exists. The existing root child-list observer handles imported runtime reconstruction. No protected JavaScript bundle, SPA routing or alternate runtime strategy was introduced.

The section-scoped entrance script uses IntersectionObserver and native animation for the supplied 600ms/150ms fade/translate sequence. It initializes each card once, including newly projected clones, and respects reduced-motion preferences. Without the required browser APIs, content remains visible. It is not a carousel controller.

## 23–25. Actual Apache fixture and evidence

Temporary fixture, explicitly fictional:

1. **Asha Test Client**, **Fictional Test Location 1**, “Fictional verification quote 1. This is test content, not a customer review.”
2. **Neema Test Client**, **Fictional Test Location 2**, corresponding quote 2.
3. **Juma Test Client**, **Fictional Test Location 3**, corresponding quote 3.

Eyebrow: **Client Stories Test Fixture**. Heading: **Apache Client Stories Verification**. Portrait slots temporarily reused ready MediaAsset `01m1p3dkfhrt7cagz0byn9jvd7`, with the explicit alt **Test fixture image, not a client portrait**. This was a projection fixture, not published client testimony.

Raw Apache `/` returned HTTP 200 and contained the exact managed names, complete quotes, locations and canonical Media URL in position order. The active section contained three cards and none of James M., David K. or Marcus A. The response included the inert managed template and synchronizer, with Women's Handbags before, Follow the Journey after, and the previously managed Summer Edit projection still present.

Canonical fixture URL: `https://res.cloudinary.com/workwue1/image/upload/c_fill,w_320,h_320,q_auto,f_auto/local/william-taylor/media/2026/09/01M1P3DFY72HPDEJ1P9SJ97C4Z`.

Evidence files: `storage/app/client-stories-apache-managed.html` and `storage/app/client-stories-apache-evidence.json`. JSON includes full fixture values, URLs, order checks and full/partial/zero HTTP results.

All prior section fields, rows and MediaUsage records were restored in `finally`. The resulting state is **unmanaged**, original eyebrow/heading, zero managed story rows. A fresh Apache request returned HTTP 200, original three static identities, no fictional test identity and no managed projection template. No fictional client content remains configured.

## 26–28. Empty states, responsive behavior and accessibility

Full: three eligible visible cards. Partial: only eligible visible cards, maintaining position order and supplied column widths. Zero: hidden managed section marker, no template filler. Missing attribution, required portrait/alt, hidden stories and archived/unready images are excluded by the presenter. Direct fixture checks confirmed two-card output after hiding position two and a hidden section after hiding all positions.

Served section-scoped CSS explicitly retains the 32px grid gap and one/three-column breakpoints. `minmax(0,1fr)`, `min-width:0` and text wrapping protect long content without body overflow clipping. Portraits do not shrink. Card/section typography, padding and background remain template-derived; there is no Product-card reuse.

Managed cards use `figure`, `blockquote` and `figcaption` semantics, meaningful portrait alt and decorative quote/medallion suppression. There are no navigation controls needing carousel keyboard behavior. Motion is optional and reduced-motion aware. Physical viewport measurements and animation acceptance at 430px, 768px, 1024px and desktop remain pending browser availability.

## 29–32. Focused validation

- **4 PHPUnit tests, 122 assertions passed**: three Client Stories scenarios and the existing canonical managed Women's Handbags scenario as a tiny adjacent regression.
- Coverage: access denial, editor shell, exact three-position capacity, plain-text validation, invalid-input retention, create/update persistence, stale-save rejection, audit, visibility, Media/alt fallback, missing alt, full/partial/zero projection, canonical order, original-template suppression, workspace order and unmanaged restoration.
- Scoped PHPStan for the new model/controller/presenter and changed Homepage model/public controller: **zero errors**.
- Changed-file Pint, PHP syntax, changed inline JavaScript syntax, Blade compilation and scoped `git diff --check`: **passed**.
- Apache managed/full, partial, zero and restored `/`: **HTTP 200**; all recorded output checks passed.
- Browser: one availability attempt returned `[]`. No retry, screenshot, physical acceptance or full matrix claimed. Final interactive DOM behavior is supported by code inspection and served projection evidence, not a browser acceptance claim.

## 33–35. Boundaries

Women's Handbags was not redesigned. Follow the Journey, Inner Circle and Sign the Ledger were not started. Customer Accounts, Product Reviews, Shop migration, Inventory, Cart, Checkout and payments were not started. No full suite, full Larastan, dependency audit, full build, full fidelity matrix or database reset ran.

Root `AGENTS.md` requires `AUTHORIZE_BE6A1_FULL_AUDIT` before a full audit. Focused checkpoint success does not authorize it.

**ECOM-HOME-10 IMPLEMENTATION READY FOR GENERAL INSPECTION**
