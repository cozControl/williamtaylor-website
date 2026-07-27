# BE-4G.1 Architecture Gap Analysis

Date: 2026-07-25
Status: Pre-change assessment

This assessment compares the implemented BE-4G foundation with the separately authorized BE-4G specification. It does not redefine that specification.

| Requirement | Classification | Evidence and required correction |
|---|---|---|
| Resource-type model | Contradictory | One `global` record and schema currently combine every business concept. Introduce code-owned `primary_navigation`, `footer_navigation`, `announcement`, and `site_profile` resources. |
| Singleton behavior | Reusable with correction | `site_contents.key` is unique, but type and per-locale singleton constraints are absent. Add `type`, `title`, archive metadata, and type-aware constraints/actions. |
| Multi-record announcements | Contradictory | Announcements are array members in the singleton payload. Split them into independently governed ULID resources. |
| Permission matrix | Contradictory | `navigation.manage` and Page workflow permissions govern the combined resource. Replace them with the exact Navigation, Announcement, and Settings permissions. |
| CMS Manager bundle | Reusable with correction | The approved role exists and assignments remain reusable. Replace the current Site Content permissions with the complete granular bundle. |
| Self-approval policy | Contradictory | CMS Manager self-approval is globally prohibited. Implement a code-owned policy contract with approval allowed for all four production types and test a prohibited fixture mode. |
| Primary navigation schema | Reusable with correction | Link validation and limits exist, but stable keys, typed links, children, depth, visibility, HTTPS-only external URLs, and reorder semantics are missing. |
| Footer navigation | Contradictory | Footer links appear in both generic menus and arbitrary footer columns. Replace with bounded storefront-grounded groups without nesting. |
| Announcement overlap | Missing | No independent schedules or collision detection exist. Implement blocking overlap checks at scheduling and execution. |
| Site profile | Reusable with correction | Identity, contact, WhatsApp, social, and footer fields exist, but the schema includes global settings and lacks typed media roles and stricter platform/link rules. |
| Media usage | Missing | Current Site Content revisions do not create revision-owned Media usage records. Add type-defined media roles and transactional synchronization. |
| Administration workspaces | Contradictory | One combined Livewire workspace exists. Replace it with Navigation, Announcements, and Site settings business workspaces. |
| Routes | Contradictory | One combined administration route and one admin-prefixed preview exist. Implement the authorized named routes and one typed `/preview/site-content/...` route. |
| Secure preview | Reusable with correction | Authentication, authorization, signing, immutability, no-store, and noindex exist. Change authorization and rendering to the selected resource type. |
| Revision comparison | Missing | Current UI exposes revision history without a complete type-specific semantic comparison. Add registry-owned comparison strategies. |
| Scheduling | Reusable with correction | UTC scheduling, command, job, overlap protection, and job identity exist. Make authorization and execution resource-specific and add archive/collision guards. |
| Audit | Reusable with correction | Bounded workflow audit infrastructure exists. Align event names, type-specific permissions, archive/restore events, and registry-alignment evidence. |
| Fingerprints and concurrency | Reusable with correction | State fingerprints and draft lock versions exist. Expand fingerprints with type-policy and registry checksums. |
| Browser evidence | Contradictory | Evidence covers only the combined workspace and uses the configured local database. Replace with isolated evidence for all business workspaces, permissions, workflows, and viewports. |
| Public-projection boundary | Compliant | No public controller, cache, API, or template currently reads Site Content. Preserve this boundary and rerun fidelity checks. |
| `willy` preservation | Contradictory | PHPUnit uses memory SQLite, but browser evidence and local application commands used tracked `willy`. Establish its current baseline, isolate all evidence databases, add refusal checks and a preservation wrapper. Historical preservation cannot be claimed. |
| Data preservation | Missing | No deterministic splitter exists for a legacy `global` payload. Add an idempotent alignment action that creates new immutable revisions without mutating old revisions. |
| Archive and restore | Missing | Site Content has no archive metadata or announcement archive/restore actions. Add them for announcements only. |
| Unknown type handling | Missing | The current schema has no type registry. Unknown types must fail closed. |
| Public localization UI | Not applicable | BE-4G.1 fixes locale constraints internally but does not authorize localization UI. |

## Reusable foundation

The existing generic ULID resource record, immutable `ContentRevision`, separate publication-state pointers, append-only transitions, transactional workflow structure, stale-confirmation fingerprint, scheduler command/job shape, audit action, and signed preview controls are retained where they satisfy the corrected type boundary.

## Required correction order

1. Isolate validation from `willy` and record its accepted current baseline.
2. Add the type registry and corrected schema constraints.
3. Split legacy global data through an idempotent alignment path.
4. Replace permissions and alignment metadata.
5. Make draft, workflow, preview, schedule, archive, and comparison behavior type-specific.
6. Replace the combined administration workspace and routes.
7. Expand focused and browser evidence before closure.

