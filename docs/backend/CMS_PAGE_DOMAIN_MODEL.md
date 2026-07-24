# CMS Page Domain Model

`pages` is a ULID aggregate containing type, locale, title, draft slug, template key, current draft revision, actors, and reversible archive state. It has no publication columns or public route.

`PageTypeRegistry` owns `standard` and `landing`. `TemplateRegistry` owns `standard_page` and `editorial_landing`. Registry values define compatible templates, sections, limits, preview renderers, and future renderer identifiers; database values never select arbitrary Blade files or classes.

Page creation validates all registry and slug inputs, creates the page and initial immutable revision, moves the draft pointer, and records `content.page.created` in one transaction. Archived pages return HTTP 409 from the edit route and are rejected by the save action.

Archive and restore require non-empty reasons and their dedicated permissions. They preserve all revisions, never publish, and record `content.page.archived` or `content.page.restored`.
