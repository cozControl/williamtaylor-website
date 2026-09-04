# CMS-PROD-1 Media Diagnosis

Date: 2026-09-04  
Status: implementation complete; physical browser acceptance pending

## Root cause

The direct Cloudinary upload returned an ISO-8601 `created_at` value. `CloudinaryMediaProvider::verifyUploadResult()` instead read a non-existent `created_at_timestamp` field, converted the missing value to `0`, and rejected every real upload as stale. `MediaLibrary` then caught the runtime exception and replaced the cause with one generic confirmation message.

The repaired contract:

1. Requires the provider response fields needed to identify and verify the upload.
2. Parses Cloudinary `created_at` and enforces the five-minute confirmation window plus a one-minute future-clock tolerance.
3. Verifies Cloudinary's response signature over `public_id` and `version` with the configured API secret.
4. Enforces the configured folder prefix, upload delivery type, resource type, MIME type, exact expected byte count, format and maximum size.
5. Derives the MIME type from resource type/format when Cloudinary omits it (`jpg` maps to `image/jpeg`).

Signature validation was not weakened.

## Intent security

`media_upload_intents` stores a random ULID reference, actor, selected provider, generated public ID, resource type, MIME type, expected bytes, expiry and consumption time. Confirmation locks that record and rejects missing, unknown/cross-actor/cross-provider, expired, consumed, public-ID-mismatched, resource-type-mismatched, MIME-mismatched or byte-mismatched evidence. Successful confirmation consumes the intent in the same transaction as Media Asset/version/audit creation. Choosing an existing exact duplicate also consumes the intent.

The browser receives only the opaque intent reference and the existing provider upload parameters. Logs never include credentials, secrets, signatures, access tokens or raw provider responses.

## Safe diagnostic taxonomy

| Code | Meaning |
|---|---|
| `media.intent_missing` | No server intent reference supplied |
| `media.intent_invalid` | Unknown, cross-actor or cross-provider intent |
| `media.intent_expired` | Server intent expired |
| `media.intent_consumed` | Replay of an already completed intent |
| `media.provider_configuration_invalid` | Required provider configuration missing |
| `media.provider_response_incomplete` | Required provider evidence absent |
| `media.provider_timestamp_invalid` | Provider timestamp malformed/stale/future |
| `media.provider_signature_invalid` | Response signature does not match |
| `media.provider_public_id_invalid` | Public ID differs from the server intent |
| `media.provider_folder_mismatch` | Public ID is outside the configured folder |
| `media.provider_resource_type_invalid` | Resource type differs from intent/policy |
| `media.provider_mime_type_invalid` | MIME differs from intent |
| `media.provider_delivery_type_invalid` | Delivery type is not `upload` |
| `media.provider_format_invalid` | Format is outside policy |
| `media.provider_bytes_invalid` | Bytes differ from intent or exceed policy |
| `media.provider_unavailable` | Unexpected provider/confirmation runtime failure |

Normal staff receive a retry message and a generated `WT-…` support reference. The warning log contains only code, intent reference, support reference, actor ID, provider name and UTC timestamp.

## Provider strategy

Keep **Cloudinary** as the explicitly configured local/staging/production provider for now. The abstraction can support a Local provider, but adding one would create a second delivery/security path without resolving the known defect. The repository's current local environment has all required Cloudinary values present. The deterministic adapter remains test-only and is prohibited in production.

A future Local adapter is appropriate only if offline development becomes a confirmed requirement. It must implement the same intent, confirmation, readiness, URL and Media Usage contracts; it must not be an implicit fallback.

## Verification

Focused automated verification proves ISO timestamp/signature success, forged signature rejection, stale response rejection, folder rejection, actor/provider/purpose/file binding, single use, READY creation and no asset creation on rejected confirmation. A real browser/provider upload could not be executed because the in-app browser exposed no controllable instance. That is the only remaining proof gap; this document does not claim that a live Cloudinary thumbnail decoded.
