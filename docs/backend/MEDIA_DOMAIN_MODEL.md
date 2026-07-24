# Media Domain Model

`media_assets` uses a ULID logical identity. `media_asset_versions` preserves provider/file facts for every replacement and has one current version. `media_usages` references the logical asset using owner type, string identifier, field role, optional locale, contextual alt/decorative/caption overrides and order. String owner identifiers support future integer and ULID owners without creating either domain now.

No binary or transformed URL is stored. Provider facts are excluded from editorial metadata actions. Archive preserves usages and blocks new attachment. Irreversible deletion is not implemented.

## BE-4D.1 invariants

Exact duplicates use authoritative checksum evidence. Reuse creates no logical record. Override requires a reason and audit. Replacement proposals are actor/asset bound and fingerprint current version plus ordered usages. Successful replacement preserves logical identity and usages while adding one immutable version.
