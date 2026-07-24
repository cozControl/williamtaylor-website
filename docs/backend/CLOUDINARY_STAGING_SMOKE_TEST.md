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
