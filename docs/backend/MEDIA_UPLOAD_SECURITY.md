# Media Upload Security

Administrative originals use signed direct browser uploads. Intents validate permission, image/video type, MIME, expected bytes, isolated prefix and five-minute expiry. SVG and raw files are excluded. Images allow JPEG, PNG, WebP and AVIF up to 20 MB. Videos allow MP4 and WebM up to 250 MB and the operational target is five minutes.

Confirmation verifies signature, freshness, prefix, delivery type, resource type, format and bytes. Browser facts cannot override provider facts. Provider credentials, signatures and raw payloads are never audited or rendered.

## BE-4D.1 queue controls

Upload intents are short-lived, permission protected, rate limited, environment prefixed, and contain no API secret. Invalid, stale, tampered, mismatched, oversized, and unsupported results fail before acceptance. Provider exceptions are rendered as safe per-item failures without signature, payload, SQL, or stack details.
