# Site Content Domain Model

`SiteContentResource` owns type, key, locale, title, draft/candidate/designated revision pointers, publication state, schedule and archive metadata. Revisions are immutable. Primary/footer navigation and site profile are locale singletons; announcements are multi-record ULID resources. Unknown types fail closed.
