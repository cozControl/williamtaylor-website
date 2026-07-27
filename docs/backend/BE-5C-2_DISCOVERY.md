# BE-5C.2 Campaign Discovery

Date: 2026-07-26

| Surface | Evidence | Owner / decision |
|---|---|---|
| `/pre-order` | PRE-ORDER label, editorial headline/copy, ordered Product cards, reserve CTA, deposit and shipping language | `pre_order` Campaign and Product targets included. Deposit/reservation/payment/shipping claims deferred. |
| `/limited-edition` | LIMITED label, editorial headline, ordered Product cards, “Only X Made/left” | `limited_edition` Campaign and Product targets included. Quantities/scarcity availability deferred to Inventory and legal evidence. |
| Homepage | Pre-order and limited rails, shipping dates, scarcity labels | Campaign concepts evidenced; delivery/scarcity claims not approved domain truth. |
| Product pages | PRE-ORDER/LIMITED labels, ship dates, scarcity statements | Future badge derives from effective Campaign; no Catalogue badge persisted. Claims deferred. |
| `/collections` | Campaign cards with meaningful images linking fixed routes | Campaign `card` Media role evidenced; this does not prove Collection targeting. |
| Factory v1/v2 | Fixed navigation links only | Preserved; no Campaign records or Factory v3. |

Registered types are exactly `pre_order` and `limited_edition`. Product targeting is supported; Collection targeting is not evidenced. The only registered factual claim is `public_window_statement`, a bounded plain-text statement requiring bounded evidence and separate legal approval. `NEW`, `SALE`, `SOLD OUT`, quantities, popularity, pricing, deposits, reservations, and guaranteed delivery/dispatch remain unsupported.

The repository has no approved Campaign legal-claims permission or production legal-approver assignment. Approval therefore fails closed.
