# BE-4G Completion Report

Date: 2026-07-25
Status: Complete after BE-4G.1 architecture reconciliation and closure validation.

The original BE-4G implementation used one global aggregate because its separately authorized specification was unavailable to that implementation turn. BE-4G.1 corrected it to independently governed `primary_navigation`, `footer_navigation`, `announcement`, and `site_profile` resources while retaining the proven immutable revision, transactional publication, scheduling, fingerprint, audit and secure-preview infrastructure.

Permissions now match the authorized matrix. `navigation.manage` is retired; Navigation, Announcements and Site Settings have exact independent permissions. CMS Manager receives the approved bundles, no role is assigned automatically, Page permissions do not govern Site Content, and current type policy permits self-approval while retaining a tested prohibition mode.

Administration now provides Navigation, Announcements and Site settings workspaces. Announcements are multi-record resources with deterministic collision handling and archive/restore. Navigation and Site Profile remain locale singletons. Public storefront projection remains inactive.

Final evidence is recorded in `BE-4G-1_RECONCILIATION_REPORT.md` and `storage/app/evidence/be-4g-1`. Browser evidence contains 26 screenshots across 1440x900, 768x1024 and 375x812 with zero console errors, warnings, failed requests, failed local assets or horizontal overflow. Authorization, keyboard reorder, focus, validation, preview and publication checks passed.

Validation passed: 171 tests/1,115 assertions, Larastan 0, scoped Pint, syntax, Blade cache, Vite build, Composer/npm audits, route and scheduler inventories, whitespace, homepage fidelity, 56/56 protected storefront files and unchanged dependency locks.

The tracked `willy` history is documented honestly. Historical pre-BE-4G bytes cannot be proven. The accepted current baseline is 401408 bytes with SHA-256 `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`. The browser, homepage and final validation guards all passed without changing it.

BE-4G may close. BE-4H was not started and still requires separate authorization.
