# DEMO-1A completion report

## Status

DEMO-1A CHECKPOINT READY.

## Workspace editability repair

The affected tracked Admin and Livewire files exist, are Git-tracked, have the Archive attribute, are not read-only, are owned by the repository user, and permit read access, direct write access, parent-directory writes, sibling temporary-file creation, and atomic replacement. No file lock or antivirus/controlled-folder denial was observed. The exact failure was `windows sandbox: helper_unknown_error: apply deny-read ACLs`, isolated to the Codex patch helper rather than Windows ACLs or Git worktree state.

A clean tracked test fixture was changed reversibly and restored byte-for-byte. Its original and restored SHA-256 were both `3FAEE407AC207FAD821DE41CCC9AB8C2EF4CD29B724CC0A51C3DF05EE3A9C60A`. Existing tracked files were then edited using a UTF-8, no-BOM workspace-local temporary file followed by atomic replacement; unrelated work was preserved.

## Demo wiring

- The protected Admin layout displays `Demo Environment — Content changes affect the client testing website only.` only when the code-owned demo configuration is active. The public storefront does not render it, and the global publication kill switch remains authoritative.
- The Admin dashboard includes a permission-aware Client Demo section covering configuration, revision states, announcement/navigation/profile/media readiness, preview availability, last editor/publication, actionable readiness failures, and links to the existing editors.
- The existing Site Content aggregate, immutable revisions, optimistic locking, signed preview, review/approval/publication actions, audit events, cache invalidation, and static fallback are reused.
- Supported site-profile Media fields now use a searchable ready-image selector showing thumbnail, filename, dimensions, readiness, effective alt text, and current selection. Save-time validation rejects unready, inactive, unconfirmed, non-image, missing-alt, HTML-alt, and forged asset identifiers without creating a revision, usage, or success audit.
- No Product, Collection, Campaign, Pricing, Inventory, or Commerce projection was introduced.

## Security and validation

Changed routes remain behind the existing `auth`, `verified`, `admin.access`, permission middleware, and server-side authorization. Focused negative tests cover guest/public visibility, inactive demo mode, protected destinations, and forged Media selection with zero mutation.

Level 1 passed: 51 tests and 246 assertions. Changed-file Pint, scoped Larastan (zero errors), Blade compilation, PHP/JavaScript syntax checks, and `git diff --check` passed.

Level 2 passed: 67 tests and 484 assertions across DEMO-1A, Site Content, Media, preview, publication, public projection, and changed Admin navigation/protection scope.

The repository-owned focused browser run `demo1a-20260728112954` passed login, banner, Client Demo dashboard, workspace editing, ready-Media selection, immutable revision creation, signed noindex/no-store preview, unchanged public output before publication, review and approval, publication, protected `/admin` footer link, unpublish/static fallback, logout, and `/admin` redirect protection. It recorded zero failed local assets and cleaned its isolated SQLite runtime and processes.

## Known limitation and impact

The imported public storefront bundle still emits a pre-existing Base44 public-settings 404/console error. It is preserved in focused evidence as a known limitation; the Admin workflow has no Base44 dependency and all required functional assertions passed.

The fixture used only an isolated temporary SQLite database and was removed. No production data, persistent demo records, permissions, roles, or routes were added. No complete audit was run.

## Track boundaries

BE-6A.1 remains deferred production-closeout evidence. BE-6A.2 remains unstarted. DEMO-1B must not begin automatically.
