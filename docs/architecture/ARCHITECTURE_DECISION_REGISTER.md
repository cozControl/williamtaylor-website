# Architecture Decision Register

Date: 2026-07-23
Status: Approved by project stakeholder on 2026-07-23, with detailed confirmations retained

All 24 ADRs were approved by the project stakeholder on 2026-07-23 as architectural direction. Explicit legal, financial, provider, infrastructure and operational confirmations remain unresolved at implementation-detail level. Implementation remains separately authorized, and material changes require a new superseding ADR.

| ADR | Approved decision | Status | First affected phase | Detailed confirmation owner |
|---|---|---|---|---|
| [001](decisions/ADR-001-DEPLOYMENT-ARCHITECTURE.md) | One domain-oriented Laravel deployable; provider contracts/adapters | Approved | 2 | Technical/operations |
| [002](decisions/ADR-002-PRODUCTION-DATABASE.md) | MySQL 8, InnoDB, utf8mb4; MySQL main CI; limited SQLite | Approved | 2 | Technical/operations |
| [003](decisions/ADR-003-IDENTIFIERS-AND-DATA-CONVENTIONS.md) | ULIDs for externally referenced records; integer minor money; UTC/BCP 47 | Approved | 2 | Technical/finance |
| [004](decisions/ADR-004-ADMIN-TECHNOLOGY.md) | Livewire 4, Flux, Blade, Tailwind, bounded Alpine under `/admin` | Approved | 3 | Technical/UX |
| [005](decisions/ADR-005-AUTHORIZATION-RBAC.md) | Spatie RBAC after package gate; explicit permissions and policies | Approved | 2 | Security/owner |
| [006](decisions/ADR-006-IMPLEMENTATION-SEQUENCE.md) | Option A: access/audit foundation before admin shell | Approved | 2 | Technical/security |
| [007](decisions/ADR-007-AUDIT-ARCHITECTURE.md) | Project-owned append-only explicit audit; revisions separate | Approved | 2 | Security/legal |
| [008](decisions/ADR-008-PUBLISHING-WORKFLOW.md) | Immutable revision state machine with resource-sensitive approvals | Approved | 2/6 | Governance/legal |
| [009](decisions/ADR-009-TYPED-CONTENT-SECTIONS.md) | Constrained versioned section registry | Approved | 6 | Product/design/content |
| [010](decisions/ADR-010-RICH-TEXT.md) | Tiptap JSON truth plus server-sanitized HTML projection | Approved | 6 | Security/content |
| [011](decisions/ADR-011-MEDIA-CLOUDINARY.md) | Official SDK behind MediaProvider; signed direct upload; production isolation | Approved | 5 | Security/operations/cost |
| [012](decisions/ADR-012-MEDIA-METADATA.md) | Asset default alt plus contextual usage override/decorative state | Approved | 5 | Accessibility/content |
| [013](decisions/ADR-013-SEARCH.md) | Scout contract with database engine initially | Approved | 6/9 | Technical/operations |
| [014](decisions/ADR-014-CACHE-QUEUE-SESSION.md) | Database queue early; Redis production readiness gate | Approved | 2 | Operations/security |
| [015](decisions/ADR-015-LOCALES.md) | English unprefixed first; localization-ready; future `/sw/...` | Approved | 6 | Content/legal/SEO |
| [016](decisions/ADR-016-SEO-OWNERSHIP.md) | Resource SEO, code schema and domain-sourced facts | Approved | 8 | SEO/technical |
| [017](decisions/ADR-017-ROUTES-AND-SLUGS.md) | Canonical `/products/{slug}`; single-hop history redirects | Approved | 8/9 | Product/SEO |
| [018](decisions/ADR-018-DOMAIN-TRUTH-OWNERSHIP.md) | Separate catalogue, price, inventory, CMS, SEO and commerce writers | Approved | 2 | Product/technical/finance |
| [019](decisions/ADR-019-AI-AND-CUSTOMER-IMAGES.md) | Post-launch permissioned tools; private consented images | Approved | 19 | Legal/security/executive |
| [020](decisions/ADR-020-DATA-RETENTION.md) | Per-class retention register; no universal 30 days | Approved | 2+ | Tanzanian legal/privacy |
| [021](decisions/ADR-021-PACKAGE-GOVERNANCE.md) | Gate every dependency; contracts and exit strategies | Approved | 2 | Technical/security/legal |
| [022](decisions/ADR-022-ENVIRONMENT-TOPOLOGY.md) | Isolated local/CI/staging/production topology | Approved | 2 | Operations/security |
| [023](decisions/ADR-023-ACCESSIBILITY-QUALITY.md) | WCAG 2.2 AA administration baseline | Approved | 3 | UX/accessibility |
| [024](decisions/ADR-024-TESTING-QUALITY-GATES.md) | Phase-declared layered test and fidelity gates | Approved | 2 | Technical/QA/security |

## Approval and change-control protocol

Approval was recorded for ADR-001 through ADR-024 on 2026-07-23. A later material reinterpretation is prohibited: it requires a new ADR that explicitly supersedes the affected record. Every implementation phase remains separately authorized and must resolve its listed detailed confirmations before relying on them.

## Approved direction with detailed confirmations pending

Tanzanian retention/statutory periods; hosting and managed MySQL/Redis capabilities; Cloudinary account, limits, costs and environment plan; ordinary CMS Manager self-approval; sensitive-resource approver ownership; initial package versions; production browser/traffic targets; Swahili launch ownership; and AI/customer-image consent/retention remain pending.