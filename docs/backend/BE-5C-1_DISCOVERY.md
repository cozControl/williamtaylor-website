# BE-5C.1 Collection Discovery

Date: 2026-07-26

| Surface | Route | Evidence | Classification | BE-5C.1 |
|---|---|---|---|---|
| Collection cards | `/collections` | Titles, descriptions, meaningful images, card links, explicit order | Genuine curated Collections plus campaign cards | Foundation only; no records/projection |
| Homepage collection/editorial cards | `/` | Meaningful images and Collection CTAs; no authoritative membership | Static presentation/future placement | Foundation only |
| Homepage Product rail | `/` | Explicit ordered Products | BE-5B merchandising placement | Excluded |
| Shop All | `/shop` | General Product grid/filter | Catalogue query | Excluded |
| Pre-Order | `/pre-order` | Timed release copy and Product rail | Campaign | BE-5C.2 |
| Limited Edition | `/limited-edition` | Scarcity claims and Product rail | Campaign | BE-5C.2 |
| Product pages | five static `/products/*` routes | “You May Also Like” | BE-5B Product relation | Excluded |
| Factory v1/v2 | manifests | Navigation/About links only; no Collection truth | Static Factory content | Preserved; no v3 |

The evidence supports one type, `curated`: manual Product membership/order, no nesting, no dynamic queries, and readiness capability. It supports two singular meaningful image roles, `card` and `hero`; both require confirmed ready images and effective plain-text alternative text.

No Collection record is created. Public templates/routes, Factory sources, Campaigns, pricing, inventory, and commerce remain unchanged.
