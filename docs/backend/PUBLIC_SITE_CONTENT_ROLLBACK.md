# Public Site Content Rollback

Set `PUBLIC_SITE_CONTENT_PROJECTION=false`, then run:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan public-site-content:check
```

Verify the thirteen public routes. Static shared chrome is restored immediately; no code, database or content rollback is needed. Do not unpublish content solely to disable projection.
