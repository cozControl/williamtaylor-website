# ADR-024 - Testing and Quality Gates

## Status

Approved

## Date

2026-07-23

## Context

Authorization, publishing, media, concurrency, commerce and fidelity require more than a single feature suite.

## Decision drivers

Risk evidence, MySQL parity, fidelity, deterministic jobs, performance/accessibility and reversible release.

## Alternatives considered

One broad suite and coverage percentage alone are rejected. Phase-declared layered gates are selected.

## Decision

Every phase declares applicable unit, feature, policy, Livewire, MySQL constraint/concurrency, adapter contract, browser, accessibility, security, public fidelity, query-budget, cache invalidation and scheduled-job idempotency tests. All phases also run full Laravel tests, Blade cache, production Vite build, Composer/npm audits as applicable, git diff --check and protected-template checksums. Public transitions retain static/Laravel/diff screenshots. Financial, inventory and publishing suites include failure/retry/race paths.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Deny-first tests cover direct requests, tampering, leakage and audit failure; fixtures contain no production secrets/personal data.

## Operational implications

Main CI uses MySQL. Browser/provider tests use controlled fixtures/fakes plus staging contract checks. Flaky critical assertions cannot be waived.

## Testing implications

Each report maps requirements to evidence and counts. Budgets are set before transition and not weakened to pass.

## Migration or rollback implications

Regression tests remain through rollback unless functionality is removed. Baselines/checksums are reviewed, never silently regenerated.

## Conditions for reconsideration

Change tools/thresholds only with evidence while preserving the property tested; new domains add gates.

## Affected phases

Applies immediately to Phase 2 and all later phases.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.

## BE-4E implementation reference

BE-4E adds focused domain, sanitizer, authorization, revision, media-usage, preview-security, query-budget, responsive browser, build, audit, route, checksum, and public-regression gates. Evidence is documented in `docs/backend/BE-4E_COMPLETION_REPORT.md`.
## BE-4E.1 closure reference

BE-4E phase 6's draft-only slice is complete and validated. The 52-point evidence record, defects corrected, remaining deployment prerequisites, and hard exclusions are in `docs/backend/BE-4E_COMPLETION_REPORT.md`. Publishing and every later phase remain separately authorized; BE-4F was not started.