# Product Content Revisions

Product editorial content is normalized and sanitized before it is stored in
immutable revisions. Each revision has a monotonically increasing number,
schema and sanitizer versions, and a deterministic checksum that excludes the
derived HTML rendering.

The current draft pointer belongs to the Product aggregate. Revision creation
locks the Product, verifies stale state, and records a bounded
`product.revision.created` event. Full descriptions and rich-text documents are
not copied into audit records.

The revision model is one of six normalized catalogue tables. Product
publication and public Product projection remain outside BE-5A.1.
