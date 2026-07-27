# Site Content Operations

Run the scheduler and database queue worker continuously:

```text
php artisan schedule:work
php artisan queue:work
```

`site-content:publish-scheduled` runs every minute without overlap and dispatches an idempotent job for due Site Content. Schedules are stored in UTC and entered/displayed as `Africa/Dar_es_Salaam`.

Duplicate, early, cancelled or superseded jobs do not publish. Transaction failure leaves the scheduled state retryable. Review `site_content_publication_transitions`, `audit_records`, failed jobs and application logs when investigating.

Rollback is a new governed draft and publication, never mutation of revision history. Public rendering and cache invalidation are intentionally absent.

## Public projection

Global projection is controlled by PUBLIC_SITE_CONTENT_PROJECTION. Use public-site-content:check and public-site-content:warm; publication and unpublication invalidate affected entries after commit. See PUBLIC_SITE_CONTENT_ROLLBACK.md.

## Factory baseline operations

Factory install is checksum-idempotent. Exact published resources are reused. Drift is restored only through preview-first factory:reset --scope=content; restoration appends an immutable revision and workflow transitions rather than rewriting history.
