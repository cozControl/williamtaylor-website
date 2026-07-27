# BE-4H-B Static About Baseline

Date: 2026-07-25

The first checkpoint introduces exactly one informational public route, `/about`, named `about`, with the editorial title `Our Story`.

The static page uses the existing storefront layout, shared chrome, typography, colour tokens, spacing conventions and licensed factory imagery. Its composition is hero, brand introduction, editorial split, three design-principle cards and closing call to action.

The complete static Blade view is `resources/views/frontend/about.blade.php`. It remains the mandatory fallback when public Page projection is disabled or cannot prove readiness.

The page contains one visible `h1`, a main landmark, ordered headings, contextual image alternatives, keyboard-accessible links and visible focus treatment. The layout uses existing responsive breakpoints and contains no fixed-width content that requires horizontal scrolling.

No protected storefront asset was changed. CMS projection was not used to establish this baseline.
## B.1 reconciliation
Static and projected output now has zero CSS-pixel geometry and page-height delta at 1440x900, 768x1024 and 375x812. Residual screenshot differences are provider-image raster only.
