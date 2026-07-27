# Site Content Review and Publishing

`navigation.manage` authorizes governed Site Content draft creation and submission. `settings.manage` is additionally required to save global settings. Existing publishing permissions authorize review, approval, publication, scheduling and unpublishing.

Global Site Content is sensitive. A CMS Manager cannot approve their own submission. A distinct authorized approver is required unless the actor is a monitored Super Administrator.

The workflow supports in review, changes requested, approved and scheduled candidates; explicit candidate supersession; immediate internal designation; schedule cancellation; and reasoned unpublish. Every successful transition and audit event shares one transaction. High-impact transitions use a SHA-256 state fingerprint.
