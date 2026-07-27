# Campaign Production Activation Checklist

BE-5C.2 supplies the governed backend foundation; it does not activate public publication.

- Assign `Campaign Claims Approver` only through an approved access decision and retain evidence.
- Ensure the approver is not the claim creator, latest material/evidence editor, or submitter.
- Approve the exact checksum and evidence; any material edit returns the claim to draft.
- Confirm an approved revision, valid schedule/timezone, eligible Products, accessible ready card image, and approved required claims.
- Re-run migrations, RBAC audit, focused MySQL tests, full CI, audits, route/schedule inventory, fidelity harnesses, and production query monitoring.
- Do not publish before a separately authorized Phase 12 projection, routing, UI, cache, rollback, and operational rollout.

Rollback must preserve Campaign, Product, Media, claim, revision, schedule, and audit truth.
