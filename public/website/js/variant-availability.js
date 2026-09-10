// Pure presentation resolver; server-side Cart validation remains a future responsibility.
(function (root) {
 root.wtVariantAvailability = function (product, selected) {
  const chosen = Object.values(selected);
  const variant = product.variants.find(item => item.values.length === chosen.length && chosen.every(value => item.values.includes(value))) || null;
  const options = {};
  Object.entries(product.options).forEach(([key, values]) => {
   options[key] = {};
   values.forEach(value => {
    // A Colour describes its full range; Sizes are evaluated against the selected Colour.
    const required = key === 'colour' ? [value.id] : [...Object.entries(selected).filter(([other]) => other !== key).map(([, id]) => id), value.id];
    options[key][value.id] = product.variants.some(item => item.is_available && required.every(id => item.values.includes(id)));
   });
  });
  return {variant, options};
 };
})(typeof window === 'undefined' ? globalThis : window);
