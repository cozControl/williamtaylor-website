# Media Operations Runbook

Configure isolated Cloudinary credentials in secret management, then supervise the existing database queue. Reconciliation jobs are idempotent, retry three times and expose safe failure state. Monitor provider quota, transformation/video costs, failed jobs and orphan uploads.

Archive never removes provider data. Restore verifies the provider asset. Irreversible deletion remains deferred pending retention, backup/export, legal hold, published-reference, tombstone and recovery-window confirmation. Public media planning is unreferenced plus 30 days, subject to final operational confirmation.

During provider outage, disable new upload/replacement, preserve existing logical records and CDN delivery, retry reconciliation after recovery, and never normalize provider failures into deletion.

## Upload and replacement operations

Administrators may cancel active queue work and retry manually up to three times. Cancellation never deletes a confirmed asset. Unapplied provider uploads enter the documented reconciliation/orphan process. Run CLOUDINARY_STAGING_SMOKE_TEST.md before deployment; irreversible cleanup remains deferred.

## Factory Media

Use factory:media-sync for preview and --apply for explicit synchronization. Provider IDs are factory-versioned under the environment folder. Reset never deletes Cloudinary binaries.
