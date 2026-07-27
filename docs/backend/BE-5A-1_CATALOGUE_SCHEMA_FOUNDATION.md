# BE-5A.1 Catalogue Schema Foundation

BE-5A.1 defines six normalized catalogue tables: `products`, `product_revisions`,
`product_options`, `product_option_values`, `product_variants`, and
`product_variant_values`.

All canonical identities are ULIDs. Products retain immutable stable keys,
revision pointers, optional default-Variant pointers, reversible archive
metadata, and optimistic lock versions. Variants retain nullable normalized SKU
and barcode values, deterministic combination fingerprints, reversible archive
metadata, and normalized option/value relationships.

The schema contains no pricing, inventory, collection, publication, commerce,
or public-projection fields. Foreign keys preserve catalogue evidence rather
than cascading Product-domain deletion.
