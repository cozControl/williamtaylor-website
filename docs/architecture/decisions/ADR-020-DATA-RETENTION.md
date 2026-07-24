# ADR-020 - Data Retention and Privacy Register

## Status

Approved

## Date

2026-07-23

## Context

Tanzania privacy cannot use one 30-day rule. Operational, legal, financial, consent and immutable evidence have different triggers.

## Decision drivers

Minimization, Tanzanian legal review, recovery, statutory duties and enforceable deletion/backups.

## Alternatives considered

Universal 30 days and indefinite retention are rejected. A per-class register with legal holds is selected.

## Decision

Adopt this initial register; “legal review” means production collection/purge is not approved until Tanzanian counsel/owner confirms it.

| Class | Owner/purpose | Trigger and recommendation | Disposal/access/audit |
|---|---|---|---|
| User accounts | Identity/service | closure + 30 days; legal review | anonymize where lawful; support access; closure audited |
| Authentication logs | Identity/security | 180 days proposed; legal review | delete/aggregate; security-only; reads audited |
| Audit records | Audit/governance | 7 years proposed; legal review | append-only archive; restricted export audited |
| Draft content | CMS/work | 180 days after archive | purge unattached; scoped editors; purge audited |
| Published revisions | Publishing/evidence | life + 7 years proposed; legal review | restricted archive; actions audited |
| Public media | Media/publication | unreferenced + 30 days | delayed provider purge; media role; audited |
| Archived media | Media/recovery | 30 days after last reference | purge provider/original; audited |
| Newsletter | Engagement/consent | unsubscribe + 30-day minimal suppression; legal review | anonymize; marketing-scoped export/delete |
| Enquiries | Engagement/service | 24 months after closure; legal review | delete/anonymize; scoped sensitive reads |
| Measurements | Customer/tailoring | withdrawal/closure + 30 days; legal review | delete; explicit permission; every read audited |
| Customer images | Private media/AI | 30-day maximum or immediate withdrawal; legal approval | provider+metadata delete evidence; every access audited |
| Generated images | AI/output | 30 days unless freshly saved; legal review | provider purge; customer/restricted support |
| Anonymous carts | Commerce/intent | 30 days inactivity | delete token/cart |
| Auth carts | Commerce/intent | 90 days inactivity proposed | delete/anonymize |
| Orders | Commerce/contract | unresolved; 7-year planning baseline | retain statutory subset; finance/support audited |
| Payments | Commerce/reconciliation | unresolved Tanzania/Pesapal/card duties | token references only; restricted every access |
| Backups | Operations/recovery | 35 daily + 12 monthly proposed | encrypted expiry; operations-only restore audited |

Legal holds are narrow, authorized and audited. Restores reapply deletion tombstones.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Verify identity/authority and hold before deletion. Minimize IP/payment/image data and audit sensitive reads.

## Operational implications

Owners implement retention jobs, exception queues, backup expiry and restore re-deletion. Backups are not ad-hoc edited.

## Testing implications

Clock-controlled retention, holds, anonymization, provider deletion, restore/re-delete and permission/audit tests apply.

## Migration or rollback implications

No data changes now. Future jobs start non-destructively/dry-run. Rollback pauses purge but preserves tombstones/audits.

## Conditions for reconsideration

Review each class with Tanzanian counsel, provider contracts, law and purpose. No universal duration overrides the table.

## Affected phases

Approval gates affected engagement, commerce and AI phases.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
