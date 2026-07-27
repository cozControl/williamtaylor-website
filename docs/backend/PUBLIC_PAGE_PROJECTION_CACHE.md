# Public Page Projection Cache

Cache identity includes the Page ID, designated revision ID and checksum, route-registry checksum, Page-template checksum, Section-registry checksum, projection schema version and locale. Relevant Media mutations invalidate every Page revision usage after commit. Publication, due scheduled publication and unpublication invalidate after commit. Draft, review and approval transitions do not invalidate.
