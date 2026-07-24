# Media Duplicate Decision Workflow

Exact duplicates are detected only from the authoritative provider checksum. Filename similarity is not proof.

On detection the queue shows the selected filename and existing asset title, type, dimensions or duration, size, state, usage count, and inspection link.

`Reuse existing asset` returns the existing logical asset. It creates no asset, version, or audit noise.

`Create separate logical asset` requires `media.upload` and a non-empty reason. It is intended for distinct rights, credit, campaign lifecycle, language metadata, or business ownership. The accepted provider result is reused without a second browser upload. The new logical asset receives the normal confirmation audit plus `media.asset.duplicate-override`, including the original and separate asset identifiers and the reason.

The final server action repeats the duplicate check. Client state cannot bypass it. Unexpected or unauthorized decisions fail server-side.
