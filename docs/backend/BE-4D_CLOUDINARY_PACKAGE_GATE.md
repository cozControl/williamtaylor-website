# BE-4D Cloudinary Package Gate

Selected `cloudinary/cloudinary_php` 3.1.3, maintained by Cloudinary under MIT. It supports PHP 8.0 and later, including PHP 8.3, and has no Laravel dependency. Packagist reported no advisories at review time. The only newly locked package is its required `cloudinary/transformation-builder-sdk` 2.1.5; existing Guzzle, Monolog and PSR packages satisfy the remaining requirements.

The official SDK is isolated behind `MediaProvider`. A community Laravel wrapper was rejected because it adds framework coupling and another maintenance boundary. Direct REST was rejected because it would duplicate signature, URL and protocol behavior. Exit consists of implementing another adapter, exporting originals/provider identifiers, and retaining Laravel-owned assets, versions, usages and metadata.

Upgrade policy is reviewed minor updates and separately tested majors. Composer audit and provider contract tests are release gates.
