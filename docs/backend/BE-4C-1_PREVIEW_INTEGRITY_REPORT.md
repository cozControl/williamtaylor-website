# BE-4C.1 Preview Integrity Report

Date: 2026-07-24

## Scope and cause

BE-4C.1 corrected two verified defects in the BE-4C effective-access confirmation flow.

First, the proposed preview was built only from proposed role bundles. Existing direct registered permissions were absent, so retained access, gains, losses, duplicate sources, and administration access could be inaccurate.

Second, the apply path recalculated the preview but discarded it. It did not prove that the administrator-confirmed state still matched current database state.

No new management capability was introduced.

## Corrected permission-source algorithm

The authoritative proposed state is now calculated from:

1. Actual registered permissions attached to each proposed registered role.
2. Existing registered direct permissions on the target.
3. The Super Administrator bypass when the proposed roles contain Super Administrator.

Unknown direct permissions remain warnings and never become registered effective permissions. Direct grants are never created, removed, converted, or normalized by this interface.

Source labels are normalized as:

- `Role: CMS Manager`
- `Role: Super Administrator`
- `Super Administrator bypass`
- `Direct grant (registry warning)`

A duplicate grant is any registered permission with more than one unique source. This covers multiple roles, role plus direct, role plus bypass, direct plus bypass, and combinations with three or more sources.

Administration access is derived only from whether proposed registered effective access contains `admin.access`. Direct `admin.access` can therefore retain administration access after a role removal without being confused with Super Administrator membership.

## Preview data and fingerprint

`EffectiveAccessPreview` now contains current and proposed roles, current and proposed effective permissions, current and proposed permission sources, gains, losses, duplicate grants, administration state, Super Administrator state, warnings, and a fingerprint.

The SHA-256 fingerprint is generated only on the server. Its normalized payload covers:

- Target user identifier.
- Current and proposed registered roles.
- Current direct registered permission names.
- Current and proposed effective registered permissions.
- Current and proposed permission sources.
- Permission gains and losses.
- Proposed administration-access result.
- Proposed Super Administrator result.
- Deterministic permission-registry checksum.
- Deterministic registered and persisted role-bundle checksum.

Associative keys and list values are stably sorted before JSON encoding. The exposed value is only the hash. Email, reason, authentication tokens, passwords, and other credentials are not fingerprint inputs.

## Confirmation lifecycle

Preview reloads the target, calculates the authoritative state, stores the normalized display data and fingerprint, and resets confirmation.

Apply reauthorizes both required permissions, validates the request and confirmation, reloads the target, and recalculates the authoritative preview. The approved `AssignRoleToUser` or `RemoveRoleFromUser` action runs only when the recomputed fingerprint matches the stored fingerprint.

On mismatch:

- No role action runs.
- No role mutation occurs.
- No role-assignment or role-revocation audit record is written.
- The displayed preview is replaced with current state.
- Confirmation resets to false.
- Operation, selected role, and reason remain.
- An assertive live-region message explains that access changed.
- Browser focus moves to that message.

The administrator must review and confirm the updated preview before trying again.

## Safeguard consistency

The component does not duplicate transactions, locking, authorization, final-Super-Administrator protection, self-lockout protection, cache reset, or audit insertion. Those responsibilities remain in the existing approved actions.

The corrected preview agrees with the conservative self-lockout rule. A retained direct `admin.access` is shown as retained administration access. Without such a retained source, role removal shows administration loss and the action remains authoritative if the actor attempts unsafe self-removal.

## Tests and evidence

Focused tests cover:

- Registered direct admin and non-admin permissions retained in proposed access.
- Unknown direct permissions excluded from registered effective access.
- Role plus direct and direct plus bypass duplicate attribution.
- Super Administrator membership separated from administration access.
- Sorted deterministic gains and losses.
- Stable, order-independent SHA-256 fingerprints.
- Registry and role-bundle checksum sensitivity.
- Successful application of unchanged state with exactly one audit event.
- Role, direct-permission, and persisted role-bundle stale-state rejection.
- Updated preview replacement, confirmation reset, preserved request fields, and absence of mutation audit on mismatch.

Browser evidence is stored under `storage/app/evidence/be-4c-1`.

## Boundaries and recommendation

No permission, role, route, migration, dependency, public frontend asset, or unrelated domain was added or changed. No direct-permission management interface exists. No user receives a role automatically.

BE-4C may finally close when the validation results recorded below remain green. BE-4D was not started.

## Validation results

The final command results and browser metadata are recorded in this report after the complete validation run.

## Final validation

- Admin suite: 25 tests, 183 assertions passed.
- Identity suite: 16 tests, 85 assertions passed.
- Full suite: 98 tests, 698 assertions passed.
- Explicit Users and Roles query-budget test: passed, 1 test and 6 assertions.
- Larastan: passed with zero errors.
- Scoped Pint and PHP syntax: passed.
- Composer validation: passed.
- Composer audit: no advisories.
- Blade compilation: passed.
- Vite production build: passed with only the existing optional Fontaine notice.
- npm audit: zero vulnerabilities.
- Route inventory: 73 routes at validation; no BE-4C.1 route was added.
- Protected-template checksum comparison: 56/56.
- Dependency-lock hashes: unchanged from the BE-4C baseline.
- Git whitespace check: passed with only pre-existing line-ending notices.
- Credential scan: no committed credential pattern found.
- No migration, role, permission, dependency, public frontend asset, or direct-permission UI was added.
- The first optimize clear ran before the disposable database schema existed and reported the missing cache table. It was rerun after migration against a fresh disposable database and passed.

Browser evidence used Chromium 149.0.7827.55 at 1440x900 and 375x812. Console errors, console warnings, failed requests, failed local assets, and horizontal overflow were all absent. The evidence confirms retained direct administration access, stale warning visibility, confirmation reset, preserved request fields, assertive live-region behavior, focus movement, and successful application after reconfirmation.

The supported in-app browser could not initialize because the Windows sandbox helper failed while applying deny-read ACLs. The controlled local Playwright fallback completed all required evidence checks.

BE-4C may finally close. BE-4D was not started.
