# Administration Accessibility Checklist

- [x] Document language is present.
- [x] Responsive viewport metadata is present.
- [x] Admin pages use `noindex,nofollow`.
- [x] A keyboard-visible skip link targets the focusable main landmark.
- [x] Every page has one visible primary heading.
- [x] Navigation and breadcrumbs have accessible labels.
- [x] Current navigation uses `aria-current="page"`.
- [x] Mobile open and close controls have explicit labels.
- [x] The mobile drawer uses the native modal dialog and Escape behavior.
- [x] Drawer close restores focus to its trigger.
- [x] Focus-visible styling is high contrast and not color-only.
- [x] Touch controls meet a 44-pixel target.
- [x] User-derived names and email addresses use escaped Blade output.
- [x] Reduced-motion preferences are honored.
- [x] Environment context is readable text, not color-only.
- [x] Desktop and mobile navigation contain the same permission-filtered entries.

Browser evidence should verify keyboard focus, open/close behavior, current state, zoom/reflow, and the CMS Manager/Super Administrator visibility difference.
