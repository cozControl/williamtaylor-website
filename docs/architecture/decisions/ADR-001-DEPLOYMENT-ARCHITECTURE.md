# ADR-001 - Deployment Architecture

## Status

Approved

## Date

2026-07-23

## Context

The repository is one Laravel 13 application with Blade public rendering, Livewire/Flux interfaces, Fortify authentication, Eloquent persistence and Laravel queues. No scale or ownership evidence justifies distributed deployment.

## Decision drivers

Transactional consistency, low operational burden, frontend fidelity, provider isolation and a path to later scaling.

## Alternatives considered

One modular Laravel deployable is selected. Microservices are rejected because load and ownership boundaries are unproven. A separate CMS SPA/API is rejected because it duplicates authentication, rendering and deployment.

## Decision

Deploy one Laravel application organized into domain modules. HTTP and Livewire adapters validate and invoke application actions; actions orchestrate use cases and transactions; domain code owns invariants; Eloquent maps persistence; provider adapters implement project contracts. Public and admin rendering have separate layouts/view models. Laravel jobs remain in this deployable unless measured scale proves extraction necessary.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

This reduces network trust boundaries but does not replace authentication, authorization, CSRF, validation and escaping. Provider secrets remain server-side and signed browser operations are narrow.

## Operational implications

Operate one release with independently scalable web, scheduler and worker processes. Monitor queues, adapters and provider failures.

## Testing implications

Test dependency direction, interface authorization, action invariants/transactions, adapter contracts and public fidelity.

## Migration or rollback implications

No current migration is needed. Future extraction starts with stable contracts/events and an incremental strangler path. Rollback redeploys the prior single-app release.

## Conditions for reconsideration

Reconsider for sustained independently scaling workloads, regulatory isolation, materially different availability needs or stable separate team ownership.

## Affected phases

First affects Phase 2 and governs all later phases.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
