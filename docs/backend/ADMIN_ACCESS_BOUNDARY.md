# Administration Access Boundary

## Request boundary

`routes/admin.php` applies:

1. `auth`
2. `verified`
3. `can:admin.access`
4. A destination-specific `can:*` middleware where required

The `User` model implements Laravel's `MustVerifyEmail` contract so the approved `verified` middleware is effective. Existing Fortify login, registration, verification, recovery, passkey, two-factor, settings, and logout behavior remains on the existing user model and `web` guard.

## Expected outcomes

- Guest: redirected to login.
- Authenticated but unverified administrator: redirected to the verification notice.
- Verified user without `admin.access`: HTTP 403.
- CMS Manager: Dashboard, Audit log, and Settings only.
- Super Administrator: all five destinations.

The navigation is convenience only. Server-side middleware remains authoritative.

No administrator is assigned automatically, no credentials are stored, and no alternate guard or administrator model exists.
