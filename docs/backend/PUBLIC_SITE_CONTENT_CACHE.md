# Public Site Content Cache

Projections are cached independently. Identity contains projection schema version, resource type and ID, designated revision ID and checksum, locale, type-registry checksum, and relevant Media identity. Cache access failure attempts a direct validated build.

Published and unpublished transitions schedule resource invalidation with `DB::afterCommit`. Scheduled publication uses the same publish path. Draft save, review, approval, future scheduling and preview do not invalidate. Media identity changes make old entries unreachable; indexed resource keys are removed idempotently and unrelated cache entries are preserved.
