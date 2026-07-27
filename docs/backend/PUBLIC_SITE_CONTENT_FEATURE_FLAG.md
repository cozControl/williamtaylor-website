# Public Site Content Feature Flag

`PUBLIC_SITE_CONTENT_PROJECTION=false` is read only by `config/public_site_content.php`. It defaults to false, cannot be changed through requests or CMS forms, and controls all global Site Content projection.

After changing it, run `php artisan optimize:clear`, `php artisan config:cache`, `php artisan public-site-content:check`, and `php artisan public-site-content:warm`.

## Factory baseline

Factory installation does not change PUBLIC_SITE_CONTENT_PROJECTION. The default remains false; enabling projection remains an environment deployment decision after content readiness checks.
