# Product Domain Model

The Product aggregate is implemented by six normalized catalogue tables.
`Product` owns immutable revisions, ordered options and values, and explicit
Variants. A Variant is never generated automatically. Zero-option Products may
have one explicit base Variant whose fingerprint is the SHA-256 of an empty
normalized combination.

`ProductStateFingerprint` and `VariantStateFingerprint` protect aggregate
commands against stale state. State-changing actions lock the Product and the
specific child row, validate ownership, mutate transactionally, increment lock
versions only for effective changes, and emit bounded audit evidence.

Archive operations preserve identities, content, relationships, Media usages,
and provider assets. Restore operations are explicit and never publish, assign
a default Variant, or infer availability.

## Metadata update semantics

`ChangeProductSlug` is transactional, fingerprint-stale-safe, archive-aware, normalized with the established slug rule, idempotent for the same normalized value, and converts SQLSTATE uniqueness races into a deterministic domain validation error. It preserves every revision and child relationship, creates no redirect, and emits bounded `product.slug.changed` evidence.

`UpdateProductOption` and `UpdateProductOptionValue` change only their display label and ordering position. Option and value keys are identity-bearing and immutable in ordinary updates. This rule preserves normalized Variant selections and fingerprints without a hidden cascade. Both actions lock the full ownership chain, reject archived or stale state, preserve defaults and relationships, create no Variants, and emit bounded before/after audit summaries.

These display-only changes do not promote or publish a Product and do not require readiness mutation. Lifecycle actions and explicit catalogue-status synchronization remain responsible for readiness-derived status changes.