# Factory Reset Runbook

Default execution is preview-only. Supported scopes are `content`, `identities`, and `all`.

Content reset appends a factory revision and legitimate workflow history only when drift exists. Identity reset restores the configured users and exact roles; passwords remain unchanged unless `--include-password-reset` is supplied. Full reset requires `--apply`, `--backup-acknowledged`, and `--confirm="RESET WILLIAM TAYLOR TO FACTORY V1"`.

Apply is limited to local, testing, and staging and refuses the tracked `willy` database. Non-factory users, Site Content, Media, audit records, and all Cloudinary binaries are preserved. There is no production bypass and no provider deletion path.

## Factory v2

Use `--factory=william-taylor-factory-v2 --scope=content` to preview or restore the About baseline. Provider binaries are never deleted.
