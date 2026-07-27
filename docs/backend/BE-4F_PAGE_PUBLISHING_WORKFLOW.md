# BE-4F Page Publishing Workflow

BE-4F adds internal publishing governance without connecting CMS data to public routes.

The workflow is:

`draft -> in_review -> changes_requested -> in_review -> approved -> scheduled or published`

Scheduling can return to `approved`; publishing clears the candidate and designates its immutable revision as published. Unpublishing clears only that designation. Every successful transition and its audit record are written in one transaction.

Submission requires `pages.edit`; review requires `pages.review`; approval requires `pages.approve`; immediate publication requires `pages.publish`; scheduling requires both `pages.schedule` and `pages.publish`; cancellation requires `pages.schedule`; unpublishing requires `pages.unpublish`.

High-impact actions use a SHA-256 fingerprint over the locked page, candidate, public pointer, workflow version, readiness, policy, and registry state. A mismatch requires a fresh review.

Public storefront projection remains inactive.
