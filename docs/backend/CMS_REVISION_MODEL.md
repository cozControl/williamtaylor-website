# CMS Revision Model

`content_revisions` stores ULID immutable records with polymorphic ownership, monotonic resource revision number, payload schema version, normalized JSON, SHA-256 checksum, sanitizer version, summary, author, and timestamp.

The normalized payload contains page display data and ordered typed sections. It contains no Blade, PHP, CSS classes, JavaScript, SQL, arbitrary HTML, provider transformations, catalogue truth, pricing, or inventory.

`SavePageDraftRevision` locks the page, checks the expected current revision, validates metadata and sections, sanitizes rich text, validates media, creates revision-owned usages, moves the pointer, and audits `content.page.draft-saved` atomically. Audit or media failure rolls everything back. Normalized no-change saves return the current revision.

Model guards reject ordinary update and delete operations. A stale editor raises a conflict without replacing its in-memory form. Rollback, revision restore, comparison, approval, and publication are deferred.
