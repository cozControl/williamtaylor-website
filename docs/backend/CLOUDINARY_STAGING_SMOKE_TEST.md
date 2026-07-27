# Cloudinary Staging Smoke Test

Use isolated non-production credentials only. Record operator, environment, time, Cloudinary folder, asset IDs, usage, and cost. Never record the API secret.

1. Verify the environment is non-production and credentials resolve only there.
2. Confirm the configured folder contains the environment prefix.
3. Upload one approved JPEG through the Media library.
4. Verify direct browser upload and authoritative Laravel confirmation.
5. Compare stored provider facts with Cloudinary metadata.
6. Verify a code-owned named delivery transformation.
7. Replace the image with another approved image.
8. Verify two immutable versions, one current version, unchanged logical ID, and preserved usages.
9. Archive, verify provider existence, and restore.
10. Confirm no protected public frontend reference or checksum changed.
11. Archive or remove staging test media only under the approved non-production cleanup policy.
12. Record provider storage, transformation, bandwidth, and estimated cost.

Stop immediately for folder leakage, secret exposure, signature mismatch, unexpected deletion, public frontend change, or audit failure. Production deployment remains blocked until this smoke test passes in the target staging environment.

## BE-4H-A prerequisite

Before enabling public Site Content projection in staging, complete this smoke test with isolated non-production credentials and confirm upload, transformation, replacement, archive and restore. Never record credentials or signatures in evidence.

## BE-4H-0 verified closeout

On 2026-07-25, the scoped upload, provider facts, named transformation, replacement, immutable versions, archive, restore, and complete 43-asset synchronization passed. The trusted project CA bundle is `storage/ssl/cacert.pem`; TLS verification remained enabled. Archive did not delete the provider binary, and no provider deletion was performed. The final synchronization preview reported 43 reuses, zero uploads, zero deferred assets, and zero drift.
