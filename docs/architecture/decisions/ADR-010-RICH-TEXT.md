# ADR-010 - Rich-Text Editor and Storage

## Status

Approved

## Date

2026-07-23

## Context

Policies, editorial, services, FAQs and long product copy need prose. Raw HTML and inline uploads create XSS and asset lifecycle risks.

## Decision drivers

Semantic durable truth, strict server sanitization, bounded Livewire integration and provider-independent storage.

## Alternatives considered

Raw HTML is unsafe canonical truth; JSON-only complicates stable rendering; Markdown is insufficient for approved rich structures. Tiptap JSON plus sanitized projection is selected.

## Decision

Use Tiptap in a bounded Livewire/Alpine bridge. Immutable revisions store versioned canonical Tiptap JSON and server-generated sanitized semantic HTML. A project Sanitizer contract is initially backed by Symfony HTML Sanitizer, subject to the later compatibility/security/licence gate. Allow paragraphs, H2-H4, lists, links, blockquotes, bold, italic, restricted tables and approved media embeds by asset ID. Disallow scripts, styles, default iframes, classes, event attributes, forms, blobs, inline uploads and ordinary raw-HTML editing. Sanitize on save/publish and projection rebuild.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Validate client JSON, URL protocols/hosts and media access. Version sanitizer policy and never render unsanitized projections.

## Operational implications

Projection rebuilds may queue in bulk. Media resolves through the media domain. Upgrades require compatibility fixtures.

## Testing implications

Test malicious/malformed payloads, protocols, allowlist, deterministic projections, old schemas, media references, accessibility and editor browser behavior.

## Migration or rollback implications

Canonical JSON supports rebuilding or editor replacement. Keep schema/sanitizer version per revision; rollback restores prior policy without exposing unsafe HTML.

## Conditions for reconsideration

Reconsider the library on compatibility/maintenance failure; expand allowlist only for approved need with tests.

## Affected phases

First affects Core CMS; no package is installed now.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.

## BE-4E implementation reference

Implemented with application-owned Tiptap Core/Starter Kit 3.28.0 and Symfony HTML Sanitizer 7.4.14 adapters. Canonical restricted JSON and server-sanitized semantic HTML projections are stored. Tables, uploads, arbitrary HTML, and media embeds remain unavailable.