# Page Revision Comparison

Comparison is generated server-side from structured revision data. The baseline is the designated published revision, or otherwise the preceding revision.

It reports metadata, section count, stable-key additions and removals, order, section type, structured fields, rich-text change, media reference, and contextual accessibility changes. JSON strings are not compared directly. Rich text is represented by a bounded change classification and is never rendered as untrusted diff HTML.

Character-level rich-text comparison is deferred.
