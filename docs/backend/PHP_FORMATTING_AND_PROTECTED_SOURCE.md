# PHP Formatting and Protected Source

Normal first-party PHP source is governed by Pint using:

```powershell
vendor\bin\pint --test
```

`database/factory` is excluded narrowly because Factory v1 and Factory v2 are
approved immutable source baselines. Formatting those PHP data manifests would
change protected bytes without changing behavior. They are governed by the
stronger preservation gate:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/validation/protected-php-preservation.ps1
```

That command verifies the exact ten-file set, byte sizes, and SHA-256 hashes,
and fails for a changed, missing, or unexpected PHP file. Public imported assets
remain independently governed by `docs/phase-0/template-sha256.txt`, Factory
behavior/checksums by `tests/Feature/Factory`, homepage/About fidelity by their
browser harnesses, and `willy` by `willy-preservation.ps1`.

Local and CI validation must run both the Pint and protected-preservation gates.
A protected Factory source change requires explicit phase authorization,
reviewed replacement checksums, Factory regression, fidelity validation, and an
updated preservation baseline; developers must never regenerate the baseline
merely to hide drift.
