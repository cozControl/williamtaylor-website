# CMS-PROD-1 Manual Acceptance

Use a CMS Manager account and a fresh small JPG or WebP.

## A. Media

1. Login.
2. Open Media Library.
3. Select a fresh JPG/WebP.
4. Upload.
5. Confirm no 500.
6. Confirm no failed-processing message for the valid file.
7. Confirm READY.
8. Confirm the thumbnail decodes.
9. Add alt text.
10. Refresh and confirm persistence.

## B. Site Settings

11. Open Site Settings.
12. Confirm visible input surfaces.
13. Confirm no label/value collisions.
14. Confirm the Media search control is separate.
15. Confirm textareas are visible.
16. Confirm the Social editor is usable.
17. Confirm scheduling controls are readable.
18. Confirm Version history is readable.

## C. Media use

19. Open the Header/Footer Media picker.
20. Find the uploaded image.
21. Select it.
22. Save draft.
23. Refresh.
24. Confirm the selection persists.

## D. Storefront preview

25. Open Secure Preview.
26. Confirm the exact William Taylor storefront appears.
27. Confirm the real header/footer appear.
28. Confirm selected global content appears in its actual location.
29. Confirm the preview indicator appears.
30. Confirm no Admin layout appears.

## E. Removal and security

31. Remove the Media usage.
32. Save.
33. Confirm the Media Asset remains in the library.
34. Log out.
35. Confirm `/admin/media` redirects to login.

Record browser console errors, failed asset requests, any `WT-...` support reference and the tested browser/viewport. CMS-PROD-2 must not begin until this checklist passes.
