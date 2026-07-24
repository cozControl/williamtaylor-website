# CMS Rich Text Security

Tiptap JSON is canonical. Server-generated sanitized semantic HTML is a reproducible projection. Both are stored in the typed rich-text section together with sanitizer/schema version.

The browser adapter uses Tiptap Core and Starter Kit with a restricted toolbar. It permits paragraphs, H2 to H4, ordered/unordered lists, bold, italic, links, and blockquotes. H1, code, code blocks, horizontal rules, strike, arbitrary HTML, tables, images, file paste/drop, direct uploads, blobs, data URLs, scripts, styles, iframes, forms, arbitrary classes, and event attributes are unavailable.

The Laravel adapter recursively validates nodes, marks, attributes, and HTTPS/internal links, generates HTML only from supported structures, then passes the projection through Symfony HTML Sanitizer 7.4. Plain text is escaped. Rich HTML is rendered only from this server-owned projection.

Future schema changes must use an explicit re-sanitization migration and retain canonical JSON portability. See `BE-4E_RICH_TEXT_PACKAGE_GATE.md`.
