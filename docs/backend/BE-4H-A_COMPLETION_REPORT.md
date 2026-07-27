# BE-4H-A Completion Report

Status: BE-4H-A.1 closeout validation complete; closure recommended.

1. Architecture reviewed: approved ADRs, BE-4G.1 reconciliation, Site Content, Media, publishing, routing, cache, accessibility and test boundaries were followed.
2. Original pilot-selection blocker: no existing public route is an eligible informational CMS Page pilot.
3. Split-phase decision: BE-4H-A proves global chrome projection only; Page projection remains separately gated.
4. Final scope: primary/mobile navigation, announcements, footer navigation, profile, logos, contact, WhatsApp, social and newsletter copy only.
5. Chrome inventory: documented in `BE-4H-A_PUBLIC_CHROME_INVENTORY.md`; eleven route-local header variants and shared announcement/footer/newsletter/WhatsApp partials were found.
6. Files: PublicProjection DTO/services, two commands, configuration, focused tests, projected mobile/social partials, bounded Blade selectors and required documentation were added; existing chrome variants received projection selectors.
7. Domain: `app/Domain/PublicProjection` owns resolution, typed DTOs and cache behavior.
8. Feature flag: `PUBLIC_SITE_CONTENT_PROJECTION`, default false.
9. Configuration: defined only in `config/public_site_content.php`; config cache passes and requests cannot override it.
10. Injection: one singleton resolver is supplied by the frontend and frontend-layout view composer and memoized per request.
11. Revision selection: only `publicationState.currentPublicRevision` is accepted and resource ownership is revalidated.
12. Readiness: code-owned type validation, relationship checks, link validation, announcement windows and Media readiness run before rendering.
13. Static fallback: complete pre-existing surface markup remains in each selector.
14. Isolation: navigation, footer, announcement and profile fail independently.
15. Primary model: readonly navigation, item and typed-link DTOs preserve order, visibility and one child level.
16. Desktop navigation: all existing public header variants consume the designated revision when valid.
17. Mobile navigation: same revision, visibility filtering, labelled controls, `aria-expanded`, Escape, focus trap and focus return.
18. Footer navigation: exactly the approved code-owned group structure is rendered; invalid content falls back.
19. Announcement resolution: UTC effective windows, active resources only, and safe failure on multiple effective announcements.
20. Announcement projection: message, optional typed CTA, dismissibility and accessible label are projected.
21. Profile model: readonly typed profile DTO; no workflow or raw payload data is exposed.
22. Brand: name plus header/footer logo URL project independently with static fallback.
23. Contact: email, telephone, address and normalized WhatsApp target project into existing surfaces.
24. Social: ordered allowlisted HTTPS links render with accessible labels and safe external attributes.
25. Footer: description, copyright, newsletter heading and supporting copy project; no newsletter persistence was added.
26. Media: ready, non-archived logical assets resolve through the existing provider contract and bounded `site_logo` profile.
27. Alt/decorative: projected logos use the public brand name; no decorative Media surface was introduced.
28. Blade: no Eloquent query, payload decoding, workflow state or provider transformation exists in public Blade.
29. Cache: independent per-resource projections with resource key indexes and direct-build fallback. Validated scalar payloads, rather than serialized DTO objects, make the database cache driver portable.
30. Keys: include type, resource/revision IDs, checksum, locale, projection version, registry checksum and relevant Media identity.
31. Invalidation: publish/unpublish, including due scheduled publication, register idempotent invalidation through `DB::afterCommit`; drafts/review/approval/scheduling do not.
32. Warming: `public-site-content:warm` is safe and reports disabled state without querying content.
33. Check: `public-site-content:check` is safe and reports disabled state; enabled readiness is covered by focused tests.
34. Observability: bounded warning classifications and correlation IDs, without payloads or secrets.
35. Security: request revision/locale/flag selection is impossible; unsafe links are rejected by typed schemas; no internal identifiers render.
36. Accessibility: landmarks/classes are retained; projected mobile controls and social/announcement names are explicit. No full WCAG claim is made.
37. Responsive: existing breakpoints and route titles remain; disabled fidelity evidence covers all primary and breakpoint widths.
38. Administration: environment-aware enabled/disabled wording is displayed; no CMS toggle exists.
39. Deployment: deploy disabled first; MySQL, isolated Cloudinary, scheduler/queue and cache prerequisites are documented.
40. Rollback: set the flag false, clear/rebuild configuration and run the check command; no database/content rollback.
41. Focused projection tests: 9 tests, 36 assertions pass, including publication lifecycle, cache portability and query budgets.
42. Site Content regression: included in the final focused group and full suite, passing.
43. Publishing regression: included in the final focused group and full suite, passing.
44. Content regression: included in the final focused group and full suite, passing.
45. Media regression: included in the final focused group and full suite, passing.
46. Admin regression: included in the final focused group and full suite, passing.
47. Identity regression: included in the final focused group and full suite, passing. The combined focused command passes 123 tests and 721 assertions.
48. Full suite: the final post-change run passes 180 tests and 1,151 assertions.
49. Query budgets: disabled resolution uses zero queries; uncached missing-surface composition uses no more than four; uncached and cached complete composition and enabled homepage/product requests use no more than six.
50. Larastan: full project passes with zero errors.
51. Pint/syntax: scoped Pint passes; PHP and JavaScript syntax gates pass.
52. Routes: 39 application routes; no new public route was added.
53. Scheduler: scheduled Page and Site Content publication commands remain registered each minute.
54. Check result: enabled `public-site-content:check` succeeded against the disposable database and reported all required surfaces projection-ready.
55. Warm result: enabled `public-site-content:warm` succeeded against the disposable database and warmed the projection cache.
56. Composer: configuration valid with the pre-existing exact-version warning. `composer audit` passed with no security vulnerability advisories found.
57. Blade: all templates compile successfully.
58. Vite: production build passes; only the existing optional Fontaine warning remains.
59. npm audit: passed with 0 vulnerabilities.
60. Disabled screenshots: the homepage harness completed at all 11 required sizes with its existing thresholds unchanged. Results were 0.011494% at 375x812, 0.005086% at 768x1024, identical at 1440x900, 0.006955% at 639x900, 0.454514% at 640x900, 0.005650% at 767x900, 0.000289% at 768x900, 0.000543% at 1023x900, identical at 1024x900, 0.003475% at 1279x900 and 0.001910% at 1280x900. Difference images are retained.
61. Enabled browser evidence: standalone Chromium 149.0.7827.55 passed against a disposable SQLite database. Thirty-two screenshots cover nine routes at 1440x900, 768x1024 and 375x812, plus mobile-open, no-announcement, invalid-navigation, invalid-media and restored states. Navigation, announcement, footer, profile, mobile expanded state, Escape close and focus return passed. There were no failed requests, failed local assets or warnings. Two console errors are the known licensed-template Base44 public-settings 404 behavior, not introduced by projection. The pre-existing pre-order overflow at 375 pixels was recorded; all other captured states had no horizontal overflow.
62. Protected checksums: 56/56 match `docs/phase-0/template-sha256.txt`.
63. Dependencies: no diff in Composer/npm manifests or lockfiles; no package was added.
64. Scans: 1,035 added lines were checked with no credential, prohibited Page-projection symbol, em dash, mojibake or replacement-character match. Browser output contained no revision/workflow identifier leak.
65. Git whitespace: `git diff --check` passes; line-ending notices are non-errors.
66. Cloudinary: isolated staging smoke test remains a deployment prerequisite and was not claimed locally.
67. MySQL: no BE-4H-A migration exists. Release-equivalent MySQL validation remains a deployment prerequisite; the earlier MySQL-safe Media/Site Content corrections were preserved.
68. Willy baseline: 401408 bytes, SHA-256 `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`.
69. Willy guard: passed before browser work and after disposable migration/browser validation. The final closeout guard confirmed the same size and SHA-256.
70. Limitations: isolated Cloudinary and release-equivalent MySQL staging checks remain deployment prerequisites. Known template-only Base44 404 console behavior and the pre-existing 375-pixel pre-order overflow remain outside this projection closeout.
71. Bodies: every homepage, collection, shop, product, pre-order, limited-edition, gift-card, wishlist and authentication body remains static.
72. Page boundary: no CMS Page projection, Page registry, Page resolver or new public route was implemented.
73. Domain boundary: catalogue, SEO management, commerce, localization, APIs, customer images and AI were not started.
74. Recommendation: authorize BE-4H-A closure. All BE-4H-A.1 local closeout gates now pass; deployment prerequisites remain explicitly documented and do not expand this phase.
75. Later phases: BE-4H-B and BE-4I were not started.
## Verified closeout defects

Two projection defects were reproduced and corrected:

1. Database-backed cache retrieval could deserialize cached DTOs as incomplete PHP classes. The cache now stores validated scalar payloads and reconstructs readonly DTOs at the application boundary.
2. The protected storefront JavaScript remounts `#root`, which removed server-rendered projected chrome after hydration. A bounded synchronizer outside that mount preserves and reapplies only the approved header, footer and WhatsApp chrome after remount while retaining the protected bundles unchanged.

No storefront body, protected asset, route, CMS Page projection or later-phase domain was changed.

## Evidence inventory

- Browser findings: `storage/app/evidence/be-4h-a/browser-findings.json`
- Browser screenshots: 32 PNG files under `storage/app/evidence/be-4h-a`
- Disposable evidence database: `storage/app/evidence/be-4h-a/validation.sqlite`
- Homepage fidelity evidence: `storage/app/fidelity/homepage`
- Protected-template manifest: `docs/phase-0/template-sha256.txt`

The external advisory audits supplied by the client are recorded as passed: Composer reported no security vulnerability advisories and npm reported zero vulnerabilities.