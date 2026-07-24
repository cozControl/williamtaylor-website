# Media Replacement Workflow

Replacement requires `media.replace` and begins on the logical asset detail page. The page displays current preview, version facts, usage count and roles. The proposed binary is directly uploaded and provider-verified before comparison.

The comparison reports old and proposed format, dimensions, byte size, and material warnings for changed format, dimensions, size, and aspect ratio. Replacement is limited to the same image or video resource type. A non-empty reason and explicit review confirmation are required.

Laravel stores the verified proposal for ten minutes under a server token bound to actor and logical asset. Its fingerprint covers current asset/version/provider facts plus ordered usage state. Application recomputes the fingerprint transactionally. If version or usage changed, replacement stops, refreshes the comparison, preserves the reason, resets confirmation, focuses the stale-state message, and requires review again.

Successful replacement creates exactly one immutable version, switches the current version transactionally, preserves the logical asset ID and usages, retains history, and audits once. Audit or mutation failure rolls back to the previous current version. Old provider binaries are not deleted.

Cancelling forgets the proposal and creates no version. A provider binary uploaded but not applied is an orphan candidate for reconciliation cleanup under the later approved retention policy; no destructive cleanup UI exists.
