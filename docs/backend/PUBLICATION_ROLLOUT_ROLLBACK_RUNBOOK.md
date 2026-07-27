# Publication Rollout Rollback Runbook

1. Identify the affected registry resource and correlation ID.
2. Disable the global rollout switch or set only that resource to static.
3. For Page or Site Content, execute authorized emergency unpublish with a bounded reason.
4. Confirm the exact resource cache was invalidated and static output renders.
5. Compare current and historical revisions.
6. Create a rollback-to-new-draft from the selected owned historical revision.
7. Review, approve, and publish through the normal workflow.
8. Re-run shadow comparison, browser fidelity, and route-protection checks.
9. Re-enable only the affected resource and record evidence.

Avoid schema/database rollback unless the schema itself is defective. Preserve revisions, Media, workflow state, and audit history.

- Site Content: restore static chrome, then use its normal governed workflow.
- Page/About pilot: restore `frontend.about-static`, then use the Page workflow.
- Product, Collection, Campaign: not publicly enabled. Keep static; no emergency projection operation exists.

Do not re-enable a resource while browser tooling is unavailable or any generated fidelity diff remains unreviewed.
