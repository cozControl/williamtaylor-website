# User Access Workspace

## Preview integrity

Effective-access previews include exceptional direct registered grants without offering any direct-permission mutation control. Direct grants are displayed as registry warnings and remain in proposed effective state unless changed outside this workspace.

If roles, direct grants, persisted bundles, or registry-derived security state changes after preview, apply stops. The refreshed preview is displayed, confirmation is cleared, and request fields remain for re-review. The stale-preview message uses an assertive live region and receives keyboard focus.

The Users index performs paginated server-side discovery. It displays only name, login email, verification state, registered roles, effective administration entry, and account timestamps.

Search trims input, limits it to 100 characters, lowercases comparison, and escapes `!`, `%`, and `_` using a literal SQL `LIKE ... ESCAPE '!'` pattern. Name and email are searched. Results are never loaded wholesale.

Allowed filters are registered role, verification state, and administrative access. Allowed sorts are name, email, and creation date; invalid sort values fall back to name. Pagination preserves Livewire URL state.

The detail workspace contains account identity, current registered roles, effective access, a server preview and confirmation workflow, developer details, and safeguards. Passwords, passkeys, two-factor secrets, recovery codes, sessions, customer data, and commerce data are not queried or displayed.

There is no Create User, Edit User, Delete User, verification mutation, password action, or customer-profile workflow.
