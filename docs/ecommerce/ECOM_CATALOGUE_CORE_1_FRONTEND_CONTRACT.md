# ECOM-CATALOGUE-CORE-1 Frontend Contract

## Storefront evidence

The supplied templates establish five representative shapes: Oxford Shirt
(Colour and Size), Mercerized Polo (Colour and Size), Chinos (multiple Colours
and Sizes), Linen Suit (Size plus campaign-owned limited presentation), and
Executive Overcoat (Size plus campaign-owned pre-order presentation). Product
pages share gallery, title, selected/default SKU, TZS price, short copy,
structured description tabs and related cards.

## Canonical Product read model

`ProductPresenter` supplies identity, canonical category breadcrumb, formatted
base/compare-at price, effective Variant price, Product gallery, Colour galleries,
swatches, flexible options, Variants, badges, structured descriptions and ordered
related Products. Existing Blade composition remains code-owned.

Product cards must consume the same Product identity, primary Media, formatted
effective price, compare-at value and badge ownership. Availability remains
absent until Inventory is authoritative.

## Money and option contract

TZS values are stored as integer minor units using a factor of 100. A displayed
TZS 285,000 is stored as `28500000`. Variants inherit the Product base price
unless `price_override_minor` is present. Floats and formatted database strings
are prohibited.

Options are optional. The current apparel registry supports Colour and Size in
any combination, including neither. Colour values may own a swatch hex and one
ordered Media gallery shared by every Variant using that Colour.

## Ownership boundaries

Categories are structural and hierarchical. Collections remain curated groups.
Campaigns own PRE-ORDER, LIMITED, dates and scarcity claims. Inventory will own
stock availability. Shipping/returns messaging remains global unless a later
evidenced Product exception requires a typed override.
