# Administration Design System

The BE-4B shell uses project-owned tokens in `resources/css/admin.css`.

- Ink: `#171612`
- Muted text: `#706d65`
- Paper: `#f4f1e9`
- Surface: `#fffef9`
- Border: `#d9d4c8`
- Accent: `#9a7a44`
- Sidebar: `#151511`
- Focus: `#b68a42`
- Editorial headings: Georgia fallback stack
- Operational text: Instrument Sans fallback stack

The system uses thin borders, restrained contrast, editorial headings, uppercase operational labels, and generous spacing. Breakpoints are 640, 1024, and 1280 pixels. Cards move from one to two to four columns as space permits.

Flux supplies maintained profile, dropdown, menu, appearance, and script primitives. The project owns routing, authorization, layout composition, navigation data, semantic landmarks, tokens, and the native drawer behavior.

No public template CSS or JavaScript is reused or modified.
