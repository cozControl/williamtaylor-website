const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const events = {};
const display = {textContent: ''};
const decrease = {dataset: {quantityChange: '-1'}, disabled: false};
const increase = {dataset: {quantityChange: '1'}, disabled: false};
const purchase = {dataset: {variantId: 'variant-test'}, disabled: false, hasAttribute: name => name === 'data-cart-add'};
const control = {
 querySelector: selector => selector.includes('value') ? display : selector.includes('-1') ? decrease : increase,
 addEventListener: (name, callback) => {events.quantity = callback;},
 contains: button => button === decrease || button === increase,
};
const click = button => events.quantity({target: {closest: () => button}});
const drawer = {open: false, querySelector: () => ({focus() {}, addEventListener() {}}), addEventListener() {}, showModal() {this.open = true;}};
const document = {
 querySelector: selector => selector === '[data-product-quantity]' ? control : {content: 'csrf-test'},
 querySelectorAll: selector => selector === '[data-product-purchase]' ? [purchase] : [],
 getElementById: id => id === 'wt-cart-drawer' ? drawer : id === 'wt-cart-state' ? {textContent: '{"item_count":0}'} : {},
 addEventListener: (name, callback) => {events[name] = callback;},
};
const requests = [];
const context = vm.createContext({document, window: {addEventListener() {}}, MutationObserver: class {observe() {}}, fetch: async (url, options) => {
 requests.push({url, ...JSON.parse(options.body)});
 return {ok: true, json: async () => ({cart: {item_count: 3}, html: '', message: 'Added'})};
}});
vm.runInContext(fs.readFileSync('public/website/js/product-quantity.js', 'utf8'), context);
assert.equal(display.textContent, '1');
assert.equal(decrease.disabled, true);
click(decrease);
assert.equal(display.textContent, '1');
click(increase); click(increase);
assert.equal(display.textContent, '3');
assert.equal(purchase.dataset.cartQuantity, '3');
assert.equal(decrease.disabled, false);
vm.runInContext(fs.readFileSync('public/website/js/cart.js', 'utf8'), context);
events.click({target: {closest: () => purchase}, preventDefault() {}, stopImmediatePropagation() {}});
assert.deepEqual(requests[0], {url: '/cart/items', variant_id: 'variant-test', quantity: 3});
click(decrease);
assert.equal(display.textContent, '2');
console.log('Product quantity controls and Cart request: 8 assertions passed');
