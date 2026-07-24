# ADR-019 - Customer Image and AI Readiness

## Status

Approved

## Date

2026-07-23

## Context

AI shopping/styling is post-launch. Customer images are sensitive and must not enter public media or grant model database access.

## Decision drivers

Consent/deletion, capability isolation, confirmation, cost/moderation and no fit deception.

## Alternatives considered

AI database access and public Media Asset reuse are rejected. Permissioned tools plus a private image domain after launch are selected.

## Decision

Implement no AI before post-launch discovery. Customer images are private with affirmative purpose consent, signed authenticated delivery, approved retention and immediate withdrawal deletion; no public library or training reuse. AI uses permissioned schema tools, never database access. Search, recommendations, cart commands and image generation are separate permissions. Cart writes require explicit confirmation/idempotency and transaction-time price/stock. Generated imagery is labelled illustrative, not a fit guarantee. Moderation, rate/cost limits, minimized logs, provider isolation, evaluation, fallbacks and kill switches are mandatory.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Threat-model injection, cross-customer access, provider retention, biometric inference, unsafe images and unauthorized cart writes. Withdrawal propagates deletion.

## Operational implications

Provider contracts, queues, budget and incident operations precede pilot. Thirty days is a proposed customer-image maximum pending legal approval.

## Testing implications

Test contracts, consent/deletion, isolation, injection, confirmation/idempotency, stale facts, moderation, kill switch, cost and failures.

## Migration or rollback implications

Pilot disablement preserves required audit/deletion jobs. Provider deletion/export evidence is required.

## Conditions for reconsideration

Only reconsider after stable launch, legal/privacy review, DPIA-like approval and measurable value.

## Affected phases

Post-launch AI discovery and separately approved pilots only.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
