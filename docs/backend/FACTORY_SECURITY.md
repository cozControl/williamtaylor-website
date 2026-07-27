# Factory Security

Factory credentials are environment-only and validated before user mutation. Passwords are handled by Laravel's configured hasher and excluded from output and audit evidence. Apply refuses `willy`; reset additionally fails outside local, testing, or staging.

Provider configuration is presence-checked without disclosure. Upload uses deterministic scoped IDs, overwrite false, HTTPS remote sources, an explicit host allowlist, and verified provider facts. Drift is never silently replaced.

Migrations contain no seeding, user creation, password reset, file upload, provider call, or external request. `DatabaseSeeder` does nothing unless `FACTORY_SEED_ENABLED=true`.
