# Product Readiness

Catalogue readiness is derived only from persisted Product state. The pure
evaluator performs no writes and emits no audit events. A valid draft may have
zero Variants, but `ready` requires a valid current revision, valid supported
options, complete active Variants, and a valid active default Variant.

Commands never accept a caller-supplied ready boolean. Archive and dependency
changes downgrade status to `draft` when readiness fails. Restore actions stay
draft and never promote automatically. Status synchronization is idempotent and
audited only when the persisted status changes.

Readiness spans the six normalized catalogue tables and does not infer pricing,
stock, availability, publication, or public routing.
