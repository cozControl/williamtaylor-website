# CMS Media Usage

Editors select only existing ready assets. Searchable selection displays title, dimensions, type, and accessibility state. Upload remains exclusively in the Media library; no provider ID, arbitrary URL, Cloudinary transformation, or upload field is accepted by the page editor.

Each successful revision creates immutable `MediaUsage` rows owned by that revision. `field_role` combines stable section key and approved media role. Contextual alt and decorative overrides are retained. Historical revision usages are not modified.

Archived, processing, or failed assets are rejected. Informative media requires a meaningful effective alt value from either the asset default or contextual override. Decorative preview renders an empty alt. The revision, usages, pointer, and audit share one transaction, so failure leaves no partial usage.
