# Product Options and Variants

The apparel registry supports `colour` and `size`, with at most two active
options. Variant combinations must cover every active option exactly once.
Every supplied value must be active and owned by its supplied Product option.

Combination fingerprints are deterministic and input-order independent.
Updates preserve the Variant ULID and transactionally replace normalized
Variant/value rows only when the effective combination changes. SKU and barcode
are nullable and normalized to uppercase when supplied.

Archiving a default Variant clears the Product pointer and selects no
replacement. Restore revalidates the preserved relationship rows against the
current Product configuration. Option and value restore actions restore only
their target record. No action creates a Variant automatically.

These relationships are stored across six normalized catalogue tables.

## Metadata update semantics

`ChangeProductSlug` is transactional, fingerprint-stale-safe, archive-aware, normalized with the established slug rule, idempotent for the same normalized value, and converts SQLSTATE uniqueness races into a deterministic domain validation error. It preserves every revision and child relationship, creates no redirect, and emits bounded `product.slug.changed` evidence.

`UpdateProductOption` and `UpdateProductOptionValue` change only their display label and ordering position. Option and value keys are identity-bearing and immutable in ordinary updates. This rule preserves normalized Variant selections and fingerprints without a hidden cascade. Both actions lock the full ownership chain, reject archived or stale state, preserve defaults and relationships, create no Variants, and emit bounded before/after audit summaries.

These display-only changes do not promote or publish a Product and do not require readiness mutation. Lifecycle actions and explicit catalogue-status synchronization remain responsible for readiness-derived status changes.