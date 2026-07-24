# BE-4E Rich Text Package Gate

Date: 2026-07-24

## Decision

Approve the following narrowly scoped dependencies:

| Package | Version | Maintainer | Licence | Purpose |
|---|---:|---|---|---|
| `@tiptap/core` | 3.28.0 | Tiptap maintainers | MIT | Headless editor and schema runtime |
| `@tiptap/starter-kit` | 3.28.0 | Tiptap maintainers | MIT | Approved semantic node and mark extensions, configured with prohibited features disabled |
| `symfony/html-sanitizer` | 7.4.14 | Symfony | MIT | Server-side allowlist sanitization behind the project sanitizer contract |

Symfony HTML Sanitizer 8.1 is rejected because it requires PHP 8.4.1 while this project is on PHP 8.3. Version 7.4.14 supports PHP 8.2 and later and is compatible with the current runtime.

## Compatibility

- Tiptap 3 is framework-neutral and integrates through a bounded application-owned JavaScript adapter. It does not replace Livewire, Alpine, Blade, Flux, Tailwind, or Vite.
- Starter Kit depends on Tiptap's official extensions and ProseMirror packages. No React, Vue, general JavaScript framework, upload widget, page builder, or CMS framework is introduced.
- Symfony HTML Sanitizer 7.4 requires `ext-dom`, `league/uri`, `masterminds/html5`, and Symfony deprecation contracts. Composer resolves these against PHP 8.3 and the Laravel application.
- Canonical editor JSON and sanitized semantic HTML remain portable application data. No vendor-specific HTML is canonical truth.

## Security and advisories

Registry metadata and security audits must pass before closeout. The application allowlists paragraph, H2-H4, ordered and unordered lists, bold, italic, blockquote, and HTTPS or validated internal links. H1, raw HTML, code blocks, scripts, styles, iframes, forms, classes, event attributes, data URLs, blobs, and inline uploads remain disabled.

## Why native editing is insufficient

`contenteditable` alone does not provide a deterministic versioned document schema, reliable JSON normalization, constrained node/mark rules, or durable migration boundaries. Tiptap provides the approved structured document model while application code remains responsible for validation, authorization, sanitization, media references, and persistence.

## Why unrestricted HTML is rejected

Raw HTML permits executable attributes, unsafe URLs, arbitrary layout changes, fragile markup, and content that cannot be migrated safely. The approved architecture stores validated Tiptap JSON and a rebuildable server-sanitized HTML projection.

## Upgrade and exit strategy

Pin compatible major versions and review changelogs, advisory output, schema fixtures, sanitation fixtures, and browser behavior before upgrades. The project adapter and sanitizer contracts prevent package APIs from entering the domain. Tiptap can be replaced by transforming the versioned canonical JSON, and the HTML projection can be rebuilt from stored JSON under a new sanitizer policy.

No table or embedded-media editor extension is installed in BE-4E. Media remains a typed section field selected from the existing library. A table extension requires a later evidenced package gate and schema migration.
