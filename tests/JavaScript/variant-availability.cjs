const assert = require('node:assert/strict');
require('../../public/website/js/variant-availability.js');
const resolve = globalThis.wtVariantAvailability;
const p = {options:{colour:[{id:'black'},{id:'ivory'}],size:[{id:'s'},{id:'m'},{id:'l'}]},variants:[['black','s',true],['black','m',false],['black','l',true],['ivory','s',false],['ivory','m',true],['ivory','l',false]].map(([c,s,a])=>({id:c+s,values:[c,s],is_available:a,sku:c+s,price:c==='black'?'100':'200'}))};
let state=resolve(p,{colour:'black',size:'m'});assert.equal(state.variant.id,'blackm');assert.equal(state.variant.is_available,false);assert.deepEqual(state.options.size,{s:true,m:false,l:true});
state=resolve(p,{colour:'ivory',size:'m'});assert.equal(state.variant.sku,'ivorym');assert.equal(state.variant.price,'200');assert.deepEqual(state.options.size,{s:false,m:true,l:false});
for(const key of ['colour','size']){const single={options:{[key]:[{id:'a'},{id:'b'}]},variants:[{id:'a',values:['a'],is_available:false},{id:'b',values:['b'],is_available:true}]};assert.equal(resolve(single,{[key]:'a'}).variant.is_available,false);assert.equal(resolve(single,{[key]:'b'}).variant.is_available,true);}
assert.equal(resolve({options:{},variants:[{id:'default',values:[],is_available:false}]},{}).variant.is_available,false);
assert.equal(resolve(p,{colour:'black',size:'missing'}).variant,null);
console.log('Variant resolver: 12 assertions passed');
