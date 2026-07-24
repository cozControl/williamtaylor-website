# CMS Section Registry

The project-owned registry permits only `hero`, `editorial_split`, `promotional_cards`, `rich_text`, and `cta`. Definitions include labels, descriptions, schema version, page compatibility, bounded fields/items, media roles, accessibility rules, preview view, and normalization.

Each section instance carries a stable ULID key. Reordering and editing preserve it; duplication creates a new key; duplicate keys fail validation. Unknown types, schema versions, fields, arbitrary HTML/classes/scripts, unsupported page combinations, invalid links, excessive section counts, and oversized repeaters fail server validation.

CTA links are typed as `internal_path` or `external_url`. Internal links begin with one `/` and reject protocol-relative values. External URLs require HTTPS. Rendering controls external link attributes.

Section migration is explicit and versioned. Existing immutable revisions are never edited during schema evolution.
