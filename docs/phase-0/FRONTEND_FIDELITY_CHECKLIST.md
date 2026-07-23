# Frontend Fidelity Checklist

Use this checklist for every page before and after Blade extraction.

## Baseline viewports

- Mobile: 375 × 812
- Tablet: 768 × 1024
- Desktop: 1440 × 900
- Breakpoint checks: 639/640 px, 767/768 px, 1023/1024 px, and 1279/1280 px where relevant

## Visual comparison

- [ ] Document background and section order match.
- [ ] Header, announcement bar, navigation, and mobile controls match.
- [ ] Fonts, weights, line heights, wrapping, and letter spacing match.
- [ ] Colors, gradients, borders, shadows, opacity, and blending match.
- [ ] Widths, heights, spacing, alignment, grid, and overflow match.
- [ ] Images use the same source, crop, focal position, aspect ratio, and quality.
- [ ] Icons have the same geometry, size, stroke, and alignment.
- [ ] Buttons, links, fields, labels, badges, and prices match.
- [ ] Footer, mobile bottom navigation, and WhatsApp action match.
- [ ] No cumulative layout shift is introduced by dynamic data.

## Behavior comparison

- [ ] Desktop and mobile menus open, close, and focus correctly.
- [ ] Announcement dismissal behavior matches.
- [ ] Search behavior matches the supplied template.
- [ ] Product sliders/carousels and navigation controls match.
- [ ] Wishlist actions and states match.
- [ ] Product options/forms match.
- [ ] Newsletter and other forms preserve visible states.
- [ ] Videos preserve autoplay, loop, muted/audio, and plays-inline behavior.
- [ ] Hover, focus, active, loading, transition, and animation states match.
- [ ] Back/forward navigation and direct URL loading work.
- [ ] No new browser-console error or failed asset request exists.

## Content substitution safety

- [ ] Blade variables are escaped by default.
- [ ] CMS field length guidance prevents unexpected layout breakage.
- [ ] Editors cannot supply CSS classes, inline styles, or JavaScript.
- [ ] Rich text is used only where approved and sanitized with an allowlist.
- [ ] Image replacement enforces the dimensions/aspect needs of the slot.
- [ ] Empty optional fields have an approved, design-equivalent behavior.

## Approval rule

Any visible difference must be categorized as:

1. Migration defect — fix before merge.
2. Dynamic-content normalization — document in the comparison test.
3. Browser rendering variance — document with evidence.
4. Necessary change — stop and obtain written client approval before implementation.
