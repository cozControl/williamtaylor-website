# Page Scheduling Operations

Administrators enter schedules in `Africa/Dar_es_Salaam`. The application converts and persists the instant in UTC while the interface displays both the local timezone and UTC detail.

Run the Laravel scheduler continuously:

```text
php artisan schedule:work
```

Run a queue worker for dispatched publication jobs:

```text
php artisan queue:work
```

`content:publish-scheduled-pages` runs every minute and dispatches one job per due page. The job locks the aggregate, rechecks page activity, candidate identity, state, time, readiness, and policy, and records its identity. Duplicate, early, cancelled, archived, or superseded executions do nothing. Failures remain retryable because the transaction rolls back.
