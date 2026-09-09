# Site Settings save-live correction

Site Settings saving already uses `Workspace::save` -> `SaveSiteContentDraft` -> `SiteContentWorkflow::makeCurrentDraftEffective` inside a transaction. Existing edit and publish permissions, validation, revision history and audit remain enforced.

The public resolver previously returned no Site Settings whenever `public_site_content.enabled` was false, even after a successful direct publication. It now resolves the published Site Profile independently of that navigation/announcement rollout switch. It still exposes only the current public revision, never an unpublished draft. Navigation and announcements retain their existing rollout behavior.

Direct-save publication (`site-content.saved-live`) now invalidates the resource's public cache after commit, matching explicit publish/unpublish. No global cache clearing was added. Save feedback states that settings are published, and the obsolete delivery warning about enabling the rollout switch was removed.

Live read-only verification found matching saved/public Site Settings revision IDs and both email and WhatsApp populated. Both now resolve through HomepageDeliveryPresenter; the Apache homepage response contains the published email link. No contact details were fabricated or changed during this fix.

Validation: the enhanced direct-save Livewire regression passed (1 test, 8 assertions), including first save and subsequent contact change with the rollout switch off. Delivery tests passed in the nearby run. Scoped PHPStan, Pint, PHP syntax, Blade compilation and whitespace checks passed.

The nearby public-projection file exposed an existing whole-homepage query-budget failure: 36 queries against a legacy limit of 6. The enabled branch was not changed by this fix; that budget now includes the added Homepage presenters. The obsolete disabled-mode zero-query expectation was updated to one Site Profile lookup. The broad query-budget test was not weakened; its failure remains outstanding and no full-suite closure is claimed.

The previous ECOM-HOME-8 dependency on enabling the global projection switch no longer applies to Site Settings. Saving valid settings with the existing required permissions publishes them directly and makes them available to the delivery section.
