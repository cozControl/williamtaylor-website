# ADR-004 - Administration Technology

## Status

Approved

## Date

2026-07-23

## Context

Laravel already provides Blade, Livewire 4, Flux, Tailwind and Alpine. Another framework would duplicate tooling and authentication.

## Decision drivers

Existing stack, server authorization, accessibility, responsive review work and low deployment complexity.

## Alternatives considered

Livewire/Flux/Blade/Tailwind with bounded Alpine is selected. React/Vue SPA is rejected without a proven unmet requirement. Controller-only pages are less suitable for interactive review/editor flows.

## Decision

Build future administration under /admin with a dedicated admin layout that never changes the client public template. Livewire components are thin adapters; form objects hold UI validation/state; queries read and actions mutate. Alpine is limited to focus, dialogs and editor bridges. Support current stable Chrome, Edge, Firefox and Safari. Desktop supports composition, tablet review/light edit and mobile queues/approval/emergency actions. Command results are permission-filtered and edit screens protect unsaved work.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

UI visibility is never authorization. Components reauthorize mutations and protect CSRF, state tampering, mass assignment and output.

## Operational implications

Use a distinct admin asset/layout boundary and monitor Livewire payload/query cost. No shell is built in ARCH-3B.

## Testing implications

Require Livewire, policy, browser, keyboard, responsive, unsaved-change and accessibility tests.

## Migration or rollback implications

Public rendering remains untouched. A bounded embedded component precedes any future SPA proposal.

## Conditions for reconsideration

Reconsider only after a documented prototype proves a critical workflow cannot meet performance, usability or accessibility needs.

## Affected phases

First affects Phase 3, after Phase 2 access foundation.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
