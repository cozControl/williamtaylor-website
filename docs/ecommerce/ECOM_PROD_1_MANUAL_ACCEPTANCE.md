# ECOM-PROD-1 manual acceptance

## One-time setup

1. Back up the production database.
2. Run `php artisan catalogue:bootstrap-oxford --user=<existing-admin-email>` once.
3. Confirm the command reports one Product, two options and 14 Variants. Run it again and confirm it reports no changes.
4. Run the registered-access provisioning procedure used for this deployment so Product permissions exist.

## Owner workflow

1. Log in as a verified Super Administrator or CMS Manager.
2. Open **Catalogue → Products**.
3. Search for `the-taylor-oxford-shirt` and open it.
4. Confirm status is Hidden until a ready primary image is assigned.
5. Change the Product name or short/main description.
6. Under Media, select the already-uploaded ready image as Primary.
7. Select one or more different images for Gallery, give each explicit order `0`, `1`, `2` and meaningful alt text.
8. Confirm Colour shows Ivory and Noir.
9. Confirm Size shows XS, S, M, L, XL, XXL and 3XL.
10. Confirm 14 Variant combinations and a deterministic default Variant.
11. Edit one non-default SKU to a unique value.
12. Select Active and click **Save Product**.
13. Confirm success feedback, then click **View storefront**.
14. Confirm the URL is `/products/the-taylor-oxford-shirt`.
15. Confirm changed Product text and selected primary image display.
16. Click gallery thumbnails and confirm the main image changes without layout change.
17. Select Colour and Size and confirm the displayed SKU resolves from a canonical Variant.
18. Confirm price remains `TZS 285,000`, with no stock claim.
19. Confirm badges and the four related Product cards retain their existing presentation and links.
20. Check desktop and mobile widths for unchanged header, gallery proportions, controls, related grid, footer and mobile navigation.
21. Refresh both Admin and storefront and confirm changes persist.

## Negative checks

1. Guest access to `/admin/products` redirects to login.
2. An authenticated user without `admin.access` is denied.
3. A user with only `products.view` can inspect but cannot save or create.
4. Submit the same SKU on two Variants and confirm Save fails without partial changes.
5. Open the same Product in two sessions, save one, then save the stale session and confirm the refresh-before-saving message.
6. Try an archived, processing, failed, or video Media Asset and confirm it cannot be assigned.
7. Remove a gallery association and confirm the Media Asset remains in Media Library.
8. Set Hidden and confirm the public route uses the safe supplied static fallback; reactivate after the check.

## Acceptance result

Record tester, environment, date, viewport(s), selected Media Asset IDs, changed SKU, and pass/fail notes. Physical acceptance—not automated checks—closes ECOM-PROD-1.
