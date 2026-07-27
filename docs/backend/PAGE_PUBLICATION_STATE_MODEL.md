# Page Publication State Model

`Page.current_draft_revision_id` identifies the editable immutable draft. `page_publication_states.candidate_revision_id` identifies the governed candidate. `current_public_revision_id` identifies the internally designated published revision. These pointers may identify different revisions at the same time.

Candidate states are `in_review`, `changes_requested`, `approved`, and `scheduled`. Draft and published labels are derived rather than duplicated.

`state_version` increases for every successful transition. `page_publication_transitions` is append-only, uses ULIDs, and stores identifiers and bounded summaries rather than revision payloads.

An archived page cannot enter the workflow. A scheduled page must be explicitly cancelled before archive, and a published page must be unpublished first.
