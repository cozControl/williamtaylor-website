# Factory Identity Bootstrap

Set all `FACTORY_*` identity values through the environment. No usable password or real email is committed. Run `factory:install` for preview and add `--apply` explicitly.

The seeder normalizes email, reuses existing identities, verifies email, and assigns exactly one registered role through the controlled mutation boundary. Ordinary reruns preserve password hashes. Password reset requires `--include-password-reset`. Audit summaries contain role, creation state, and factory version only.

Inventory Manager is reserved and currently receives exactly `admin.access`; no future inventory permission is invented.
