# CMS-PROD-1.2 Completion Report

Date: 2026-09-04  
Decision: **CMS-PROD-1 IMPLEMENTATION READY FOR PHYSICAL ACCEPTANCE**

## Support-reference diagnosis

`WT-PK844EN6` resolves to safe code `media.provider_public_id_invalid`, upload intent `01M1NZ4YDN5MRRXQW0MXFP0GWN`, actor 17 and provider `cloudinary` at 2026-09-04 10:24:10 UTC.

The browser-to-Cloudinary upload succeeded: the upload queue invokes Laravel confirmation only after Cloudinary returns a successful HTTP response, and confirmation recovered the intent reference and provider `public_id`. Laravel then rejected the response before provider signature verification and before MediaAsset creation because the returned public ID did not equal the intent public ID.

Root cause: Laravel supplied both a complete folder-prefixed `public_id` and Cloudinary's legacy `folder` upload parameter. Cloudinary applies `folder` as a public-ID prefix, producing a double-prefixed returned ID. The repair sends one canonical server-generated full public ID and omits the separate `folder` parameter.

### Provider-field comparison

The historic safe log intentionally did not retain the full provider response. “Actually supplied” below is limited to fields proven by the executed boundary and safe failure code; secrets and signed payloads are never logged.

| Field | Expected by Laravel | Actually supplied/proven | Result |
| --- | --- | --- | --- |
| Intent context | Exact server ULID | `01M1NZ4YDN5MRRXQW0MXFP0GWN` recovered | Pass |
| Public ID | Exact intent public ID under configured folder | A different, Cloudinary folder-prefixed public ID | **Failed** |
| Version | Present | Response reached boundary; validation not reached | Not evaluated |
| Signature | Valid Cloudinary response signature for public ID/version | Present shape not retained; validation not reached | Not evaluated |
| `created_at` | Parseable fresh ISO timestamp | Present shape not retained; validation not reached | Not evaluated |
| Bytes | Exact intended byte count | Present shape not retained; validation not reached | Not evaluated |
| Width/height | Optional integers | Not retained | Not evaluated |
| Format | Approved image/video format | Present shape not retained; validation not reached | Not evaluated |
| Resource type | Exact intended image/video type | Present shape not retained; validation not reached | Not evaluated |
| Delivery type | `upload` | Present shape not retained; validation not reached | Not evaluated |
| Original filename | Provider filename or safe fallback | Not retained | Not evaluated |
| Secure URL | Not authoritative for confirmation | Not consumed | N/A |

Intent actor/provider matched, the intent existed, was fresh and unused, and context parsing succeeded; otherwise confirmation would have emitted an earlier intent-specific code. The outer database transaction began, but no MediaAsset creation/provider verification transaction began and no database mutation occurred. The intent remained unused.

## Security and retry behavior

Signature verification remains enabled and uses the installed Cloudinary SDK's `ApiUtils::signParameters` contract. ISO `created_at` freshness, signature, full public ID, configured-folder boundary, bytes, format, MIME/resource type, delivery type, version, actor, provider, expiry and single-use binding remain enforced.

Context is signed as part of the upload request. Missing/malformed context, altered/cross-intent references, cross-user intent, expiry and replay remain fail-closed.

Provider evidence is intentionally not persisted. Therefore failed items expose one clear **Try upload again** action, which creates a new intent and re-uploads. The misleading retry-confirmation action was removed. Failed queue items display filename, type/size, **Needs attention**, the bounded message and support reference.

## Admin and preview

Site Settings now ships its critical scoped control styling with the rendered component, so physical usability does not depend on an unbuilt Vite bundle. Inputs and textareas have visible surfaces, spacing, responsive width and focus rings. The Media picker separates legend, search label, search field, empty guidance and Media Library action. Two-column sections align to their natural content height and stack on mobile. Social rows render Platform, HTTPS URL, accessible label and Remove as distinct controls.

Save Draft has a dedicated change-description textarea and action row. Review/publish actions are state-aware; impossible Approve/Publish/Schedule/Cancel controls are hidden. Scheduling has a separate label, readable `Africa/Dar es Salaam` timezone, visible datetime control and “Schedule approved version” action. Version history uses separated status, summary, author and timestamp. Review changes uses staff wording such as “Brand information changed” instead of domain keys.

The CMS-PROD-1.1 preview remains unchanged in architecture: a restrained private indicator frames the unchanged `welcome` storefront, with the selected revision injected through the same `ResolvePublicSiteChrome` presenter used by published rendering. Static homepage body content remains code-owned.

## Validation

- Combined changed scope: **30 tests / 135 assertions, PASS**.
- Media/Cloudinary/Livewire: **17 tests / 68 assertions, PASS**.
- Admin Site Settings workspace: **6 tests / 41 assertions, PASS**.
- Exact storefront preview regression: **1 test / 14 assertions, PASS**.
- Scoped Larastan: PASS, 0 errors.
- Changed-file Pint: PASS.
- Blade compilation, changed PHP syntax, JavaScript syntax and `git diff --check`: PASS.

No full suite, full Larastan, complete build, BE-6A.1 fidelity, browser matrix, dependency audit or database lifecycle was authorized or run.

Protected storefront templates/imported assets remain unchanged. CMS-PROD-2 was not started.

## Remaining acceptance

The project owner must run the 35-step `CMS_PROD_1_MANUAL_ACCEPTANCE.md` checklist with a real Cloudinary upload and controllable browser. Physical acceptance and phase closure are not claimed.
