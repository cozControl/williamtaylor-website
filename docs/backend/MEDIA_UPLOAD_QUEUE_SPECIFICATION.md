# Media Upload Queue Specification

The application-owned queue uses a stable browser identifier and explicit states:

`selected -> ready -> requesting_intent -> uploading -> uploaded -> confirming -> processing|completed`

Exceptional transitions enter `duplicate_detected`, `cancelled`, or `failed`. State is never inferred from progress or error text.

Each item keeps only its file reference, filename, MIME, bytes, resource type, progress, state, safe error, short-lived intent, minimum confirmation response, retry count, accepted asset ID, and duplicate candidate. Secrets and long-lived signatures are prohibited.

Browse, keyboard selection, drag and drop, multiple selection, and later additional selections are supported. Invalid entries do not remove valid entries. JPEG, PNG, WebP, AVIF, MP4, and WebM are client-hinted and server-enforced. Raw SVG and unsupported video formats are rejected.

Uploads use native `XMLHttpRequest` for direct provider progress and abort support. Progress stops below 100 percent until Laravel confirmation succeeds. Cancellation discards local intent/result state and ignores late responses. It never deletes a confirmed asset. Administrator retries are manual, request a fresh intent, and are bounded at three; automatic retries are zero.

If binary upload succeeds but confirmation fails, the verified response may be retried while fresh. Otherwise a fresh upload intent is required. Processing entries may link to the accepted record; provider reconciliation remains queue/job owned and bounded.

Active upload or confirmation triggers a navigation warning. Completed entries do not. Local file references cannot survive browser closure.
