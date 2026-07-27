# Factory Cloudinary Synchronization

Preview with `php artisan factory:media-sync`. Apply with `--apply`; restrict a run with `--only=logical-key`. Remote video transfer additionally requires `--include-remote`.

The environment root must be scoped like `local/william-taylor/media`. Provider IDs are `{root}/factory/william-taylor-v1/{manifest suffix}`. Local checksums and remote host policy are validated before mutation. Existing matching database facts are reused; checksum drift fails closed; overwrite is disabled.

The command uses the Laravel-owned `MediaProvider`, never prints credentials, signatures, or private payloads, and creates immutable version 1 only after provider confirmation.
