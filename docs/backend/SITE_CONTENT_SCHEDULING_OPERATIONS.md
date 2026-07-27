# Site Content Scheduling Operations

Editors enter `Africa/Dar_es_Salaam` time; persistence is UTC. `site-content:publish-scheduled` dispatches per-resource idempotent jobs. Workers lock and recheck candidate, state, schedule, readiness, archive status and announcement collision. Cancelled, superseded or archived work cannot publish; duplicate workers cannot produce duplicate success transitions.

## Public projection

Due publication uses the normal publish transition and invalidates the affected public projection only after commit. Production requires the scheduler and queue worker to run continuously.

