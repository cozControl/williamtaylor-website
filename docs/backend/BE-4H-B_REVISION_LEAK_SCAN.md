# BE-4H-B.2 Browser and Revision-Leak Findings

Date: 2026-07-25

The guarded Chromium run captured all four Site Content/Page projection flag combinations at 1440x900, 768x1024 and 375x812. All 12 responses returned 200. Projected and static About rendering have identical stable geometry, page height, semantics and screenshots. There were no console errors or warnings, failed requests, failed assets, horizontal overflow, response-source leaks, live-DOM leaks or response-header leaks.

Targeted scans covered all generated response HTML and DOM dumps, Vite public JavaScript, and changed About Blade templates. Patterns included revision attributes, current-public-revision names, publication state, candidate/draft revision, checksum, workflow transition and ULID shapes. Match count was zero. Credential and Cloudinary provider-secret scans also returned zero.

Evidence is stored in `storage/app/evidence/be-4h-b/browser`.