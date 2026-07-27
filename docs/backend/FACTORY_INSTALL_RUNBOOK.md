# Factory Installation Runbook

1. Back up the target and verify it is not `willy`.
2. Configure the three factory identities and scoped Media provider.
3. Run `php artisan factory:install` and review checksums/counts.
4. Run `php artisan factory:install --apply`.
5. Run `php artisan factory:media-sync` and review upload/reuse/deferred/drift counts.
6. Complete the provider smoke test, then run the approved scoped/full Media apply.
7. Rerun both previews; content and Media should report reuse with no drift.
8. Keep `PUBLIC_SITE_CONTENT_PROJECTION=false` until a separate deployment decision.

Production remains explicit and factory reset is prohibited there.

## Factory v2

Use `--factory=william-taylor-factory-v2` explicitly to install the About baseline. V1 remains selectable and byte-identical.
