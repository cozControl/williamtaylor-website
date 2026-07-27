# `willy` Database Preservation

Date: 2026-07-25

`willy` is the local runtime SQLite database configured by the development environment. Before BE-4G.1, browser evidence used the normal application environment and therefore changed this tracked binary. No byte baseline existed before that run, so historical pre-BE-4G preservation cannot be proven.

The file was not checked out, reset, overwritten, reconstructed, or vacuumed because any such operation could destroy pre-existing local data. Its accepted post-BE-4G baseline is:

- SHA-256: `6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c`
- Size: `401408` bytes
The first BE-4G.1 guard run exposed that Artisan's `--env=testing` flag does not apply `phpunit.xml` variables. A guarded `migrate:fresh --env=testing` therefore still reached `willy`; the ending guard detected the mismatch. No historical copy existed to restore. This incident is retained in the closeout evidence rather than being reported as preservation success. A later stale evidence-server process caused a second detected mutation before the runner was hardened. The baseline above was established after that detection. All subsequent Artisan and browser commands receive an explicit disposable `DB_DATABASE`, refuse an occupied evidence port, and clean up their owned process. Final guarded browser, homepage and full validation runs preserved the current baseline exactly.

PHPUnit uses SQLite `:memory:` through `phpunit.xml`. Standalone Artisan and browser evidence must set an explicit disposable SQLite path under `storage/app/evidence`; `scripts/validation/prepare-isolated-database.ps1` creates it, and evidence fixtures refuse an empty path, `:memory:`, or the tracked `willy` path.

Run the preservation guard before and after validation:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/validation/willy-preservation.ps1 -Mode begin
# Run validation with disposable testing and evidence databases.
powershell -ExecutionPolicy Bypass -File scripts/validation/willy-preservation.ps1 -Mode end
```

Any mismatch is a hard failure. Recovery requires an owner-supplied trusted backup and separate explicit authorization. Never infer or reconstruct historical bytes from Git because the tracked file already contained unrelated local changes.

## BE-4G.1 browser-rerun incident

The first corrected browser rerun found stale PHP evidence servers still listening on the dedicated port. Requests were served by an old process using the normal runtime database, and the preservation guard detected a second change. The file was not restored or reconstructed. The newly observed 401408-byte SHA-256 is recorded in `scripts/validation/willy-baseline.json`, with the incident as its acceptance basis. The evidence runner now refuses an occupied port, starts one directly owned PHP server process, uses an explicit disposable database and ephemeral credential, and stops that exact process. Final future-preservation evidence still requires the unrelated local runtime to release its current exclusive file lock.

## BE-4H-A baseline

Accepted baseline remains 401408 bytes and SHA-256 6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c. Projection tests use disposable databases and must never target the tracked file.

## BE-4H-0

Factory commands explicitly refuse apply when the configured database basename is willy. All implementation validation uses SQLite memory or disposable evidence files. The accepted 401408-byte SHA-256 baseline remains mandatory.

## BE-4H-B

Static, projected, factory-v2 and browser evidence use disposable SQLite databases. The accepted `willy` size and SHA-256 remain unchanged.

- BE-4H-B.1 final verification: 401408 bytes; SHA-256 6feee109d13ac23f918e761bb2a996724a1dbcb7d9858e76e6875afe6d85963c.
