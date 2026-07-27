# Public Site Content Security

Public resolution accepts no revision, locale or feature state from query strings, cookies or sessions. Only code-owned type keys, locale and `current_public_revision_id` are used. Typed schemas reject unsafe links and unsupported social platforms. External links render with safe target/rel metadata. Provider credentials, raw Media metadata, workflow notes and revision identifiers are not exposed.

Ordinary public rendering does not mutate content, revisions, publication state, Media usages or audit records. Bounded warnings contain classifications and correlation IDs, not payloads or secrets.
