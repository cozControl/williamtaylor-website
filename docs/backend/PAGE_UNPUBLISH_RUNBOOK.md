# Page Unpublish Runbook

Unpublishing requires `pages.unpublish`, a current designated published revision, a current confirmation fingerprint, and a non-empty operational reason.

The action clears only `current_public_revision_id`. It preserves the page, current draft, any candidate, every immutable revision, transition history, and audit evidence. It does not archive, delete, redirect, alter a public route, or project CMS content.

After unpublishing, editors may continue drafting and submit a new candidate through the normal workflow.
