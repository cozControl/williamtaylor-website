# Homepage Frontend Fidelity Gate Runbook

## Purpose

This process compares the untouched client homepage at `public/website/index.html` with the Laravel-rendered `/` route in a real Chromium browser over HTTP.

It captures viewport screenshots, produces pixel-difference images, and records browser metadata, console messages, failed requests, failed local assets, internal-link results, and interaction findings.

The process does not modify the client HTML, CSS, JavaScript, images, or videos.

## Why Playwright is included

The Codex-supported browser process cannot start in the current Windows sandbox because ACL setup fails before browser initialization. Playwright was therefore added as a development-only dependency so a developer can run a reproducible Chromium fidelity gate from the repository root.

Production runtime dependencies were not added.

## Requirements

- Windows development environment
- PHP available as `php`
- Node.js and npm available
- Project dependencies installed
- Ports `4173` and `8000` available on `127.0.0.1`
- Network access to the licensed remote assets on `media.base44.com`

## One-time browser installation

From the repository root:

```powershell
npm.cmd install
npm.cmd run fidelity:install
```

`fidelity:install` installs Playwright's pinned Chromium build. Browser binaries are development tooling and are not committed.

## Server strategy

The capture script starts and stops both servers automatically:

- Static template: `http://127.0.0.1:4173/`
- Laravel homepage: `http://127.0.0.1:8000/`

Equivalent troubleshooting commands are:

```powershell
php -S 127.0.0.1:4173 -t public/website scripts/fidelity/static-router.php
php artisan serve --host=127.0.0.1 --port=8000
```

Do not open the template through `file://`.

The static router serves the untouched `index.html` only at `/`, serves existing template assets normally, and returns a real 404 for missing paths.

## Commands

Run the complete gate:

```powershell
npm.cmd run fidelity:homepage
```

Run capture/checks only:

```powershell
npm.cmd run fidelity:capture
```

Run pixel comparison against existing captures:

```powershell
npm.cmd run fidelity:compare
```

## Viewports

Primary captures:

- 375 Ã— 812
- 768 Ã— 1024
- 1440 Ã— 900

Breakpoint captures use a documented height of 900 pixels:

- 639 Ã— 900 and 640 Ã— 900
- 767 Ã— 900 and 768 Ã— 900
- 1023 Ã— 900 and 1024 Ã— 900
- 1279 Ã— 900 and 1280 Ã— 900

Browser zoom is 100%, device scale factor is 1, locale is `en-US`, timezone is `Africa/Nairobi`, colour scheme is light, and reduced-motion preference is enabled.

## Deterministic normalization

The following narrow normalization is applied equally after both pages finish loading:

- Wait for `document.fonts.ready`.
- Wait for every image to load or error.
- Set animation and transition durations/delays to zero.
- Hide the text caret.
- Reset scroll position to the top.
- Record each video's autoplay, loop, mute, plays-inline, and source values.
- Pause videos and seek to time zero where the remote source permits it.
- Preserve the initial announcement and carousel state.
- Use no screenshot masks.

Remote availability and browser font/rasterization differences are recorded rather than hidden.

## Generated output

All generated evidence is written below:

`storage/app/fidelity/homepage`

Structure:

```text
homepage/
â”œâ”€â”€ static/
â”‚   â””â”€â”€ <viewport>.png
â”œâ”€â”€ laravel/
â”‚   â””â”€â”€ <viewport>.png
â”œâ”€â”€ diff/
â”‚   â””â”€â”€ <viewport>.png
â””â”€â”€ reports/
    â”œâ”€â”€ capture.json
    â”œâ”€â”€ comparison.json
    â”œâ”€â”€ comparison.md
    â””â”€â”€ capture-failure.json   # only when capture fails
```

`storage/app` already ignores generated files. Screenshots and reports are therefore not committed by default. This avoids noisy, platform-dependent binary changes while the baseline is awaiting approval. CI should upload the directory as a build artifact.

## Recorded browser checks

The capture report records:

- desktop navigation presence;
- mobile menu presence and whether clicking changes visible state;
- mobile bottom navigation;
- search control and visible state change;
- wishlist action;
- bag control and visible state change;
- newsletter form, email field, and submit control;
- product slider/carousel control count;
- WhatsApp action;
- video behavior attributes;
- direct load;
- back and forward history;
- console warnings/errors;
- failed requests;
- HTTP responses at status 400 or above;
- failed same-origin CSS, JavaScript, image, media, and font requests;
- all unique same-origin homepage links and HTTP status.

Static or unresolved template behavior is reported as a finding; this phase does not implement it.

## Review and approval

1. Open `reports/comparison.md`.
2. Review every non-zero result in the corresponding `diff/<viewport>.png`.
3. Compare `reports/capture.json` findings for `static` and `laravel`.
4. Classify each difference:
   - migration defect;
   - narrowly normalized dynamic state;
   - browser/remote-asset variance;
   - necessary design change requiring client approval.
5. Reject the gate if:
   - a visible difference is unexplained;
   - Laravel introduces a console error absent from static;
   - Laravel introduces a failed local asset request;
   - required interactions differ without explanation;
   - a supplied-route link fails only on Laravel.
6. Approval requires a written reviewer, date, browser version, and explanation for every accepted non-zero difference.

No numeric threshold automatically approves a visible difference.

## Evidence the developer must return

After running the complete gate, return:

- terminal output from `npm.cmd run fidelity:homepage`;
- the complete `storage/app/fidelity/homepage/reports` directory;
- all `static`, `laravel`, and `diff` PNG files;
- the Chromium version reported in `capture.json`;
- reviewer notes for every non-zero comparison;
- `capture-failure.json` if the local browser also fails.

For convenient transfer:

```powershell
Compress-Archive -Path storage/app/fidelity/homepage/* -DestinationPath homepage-fidelity-evidence.zip -Force
```

## Known limitations

- Remote video and favicon availability depends on `media.base44.com`.
- Pixel output can vary across operating systems, Chromium versions, GPU/font configuration, and display scaling; metadata must accompany evidence.
- Captures are exact viewport screenshots, not full-page composites.
- Link checks report missing destinations already absent from the supplied template; reviewers must distinguish existing template gaps from Laravel-only failures.
- Interaction detection establishes presence and visible state change, not future backend commerce behavior.

## CI adaptation

A later CI job can:

1. Install PHP and npm dependencies.
2. Run `npx playwright install --with-deps chromium`.
3. Run Laravel tests and the production build.
4. Run `npm run fidelity:homepage`.
5. Upload `storage/app/fidelity/homepage` as an artifact.
6. Fail on browser startup, dimension mismatch, Laravel-only local asset failures, or unreviewed image differences.

Committed image baselines should only be introduced after the client or designated reviewer approves the first reproducible baseline.


## FE-2A configured page targets

The same runner now accepts a page key and stores isolated evidence per page. Clean static aliases preserve root-relative `css/`, `js/` and `images/` resolution while the static router reads the untouched source file. Missing paths still return a real 404.

```powershell
npm.cmd run fidelity:homepage
npm.cmd run fidelity:collections
npm.cmd run fidelity:shop
```

Targets: homepage `/` ? Laravel `/`; Collections static alias `/collections` (`html/page_2.html`) ? Laravel `/collections`; Shop static alias `/shop` (`html/page_3.html`) ? Laravel `/shop`. Evidence is written to matching folders under `storage/app/fidelity`. Page-specific interaction counts are recorded without claiming backend ecommerce functionality.

## FE-2B page commands

- `npm.cmd run fidelity:pre-order`
- `npm.cmd run fidelity:limited-edition`
- `npm.cmd run fidelity:gift-cards`

Limited Edition compares its native supplied route `/collections/limited-edition` with Laravel `/limited-edition`. The static router injects only a non-visual base URL for correct direct-load resolution of the source's relative assets. Use the same 11 viewports and review the generated screenshots, diffs, capture report and comparison report; thresholds and normalization remain unchanged.
