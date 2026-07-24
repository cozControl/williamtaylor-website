# ADR-023 - Accessibility and Dashboard Quality Baseline

## Status

Approved

## Date

2026-07-23

## Context

Admin workflows must work with keyboards, assistive technology and varied screens; component choice alone is insufficient.

## Decision drivers

WCAG-aligned operation, error recovery, nonvisual state, responsive review and consistent quality.

## Alternatives considered

Manual-only and automated-only testing are insufficient. Standards plus both test forms are selected.

## Decision

Target WCAG 2.2 AA. Require keyboard operation, visible focus, labels/instructions, accessible validation/summary, keyboard alternatives to drag/order, contrast, correct dialog focus, screen-reader status, reduced motion, responsive review, non-color status, purposeful empty states and unsaved-change protection. Desktop handles complex composition; tablet supports editing/review; mobile supports queues, approval and emergency unpublish with critical context. Palette/editor follow the same rules.

## Consequences

This decision removes an implementation ambiguity and preserves a replaceable boundary. It adds the governance, operational preparation, migration discipline and test obligations recorded below; those costs must be planned in the affected phase. ARCH-3B itself creates no runtime consequence.

## Security implications

Names/errors cannot expose sensitive data. Timeout/reauthentication gives recovery, and announcements never reveal unauthorized state.

## Operational implications

Build reusable tested primitives and review checklist. Core keyboard/screen-reader blockers block release.

## Testing implications

Use semantic assertions, Livewire tests, automated accessibility browser scans when selected, keyboard flows, manual screen-reader samples, zoom/reflow, reduced-motion and contrast. Drag/drop always has a tested alternative.

## Migration or rollback implications

No UI changes now. Exceptions require ADR, owner, expiry and accessible fallback.

## Conditions for reconsideration

Update for standards/browser change; do not lower essential operation.

## Affected phases

First affects Phase 3 and every admin screen.


## Approval status

Approved by the project stakeholder on 2026-07-23 as architectural direction. Legal, financial, provider, infrastructure, and operational details explicitly identified as unresolved remain subject to later confirmation. This approval does not authorize implementation outside a separately authorized phase. A material change requires a new ADR that supersedes this decision.
