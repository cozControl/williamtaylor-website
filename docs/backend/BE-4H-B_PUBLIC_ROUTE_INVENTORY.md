# BE-4H-B Public Route Inventory

Comparable method: `php artisan route:list --except-vendor --json`.

BE-4H-A had 39 application routes. BE-4H-B has 40. The only addition is `GET|HEAD /about`, named `about`, handled by `AboutController` under `web` middleware. The earlier total of 92 counted every runtime framework and package route and was therefore not comparable to 39.

No route was removed, renamed, reparameterized, or changed. No `/pages/{slug}`, `/content/{slug}`, `/api/pages`, `/api/content`, or `/our-story` route exists. The full categorized machine inventory is at `storage/app/evidence/be-4h-b/routes/inventory.json`.