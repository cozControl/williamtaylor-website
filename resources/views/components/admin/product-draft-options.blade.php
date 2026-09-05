@props(['colours' => [], 'sizes' => [], 'variants' => [], 'defaultKey' => '', 'errors' => []])

<div class="product-draft-builder" x-data="productDraftBuilder(@js([
    'endpoint' => route('admin.media.picker'),
    'colours' => array_values($colours),
    'sizes' => array_values($sizes),
    'variants' => array_values($variants),
    'defaultKey' => $defaultKey,
    'errors' => $errors,
]))">
    <div class="admin-section-heading"><p>Options & variants</p><h2>Colours, Sizes and sellable combinations</h2><p>Variants are the sellable combinations of Colour and Size. Each Variant gets its own SKU and can later have its own stock.</p></div>

    <section class="product-option-editor" aria-labelledby="draft-colours-title">
        <div class="product-option-header"><h3 id="draft-colours-title">Colours</h3><button type="button" class="admin-secondary-button" @click="addColour()">Add colour</button></div>
        <p class="admin-field-help" x-show="!colours.length">No Colours added. This is fine for a Size-only or no-option Product.</p>
        <div class="product-draft-rows">
            <template x-for="(colour, index) in colours" :key="colour.key">
                <article class="product-draft-row">
                    <div class="admin-form-grid">
                        <div class="admin-field" :class="{'admin-field-invalid': fieldError('draft_colours.'+colour.key+'.name')}">
                            <label :for="'draft-colour-name-'+colour.key">Colour name</label>
                            <input :id="'draft-colour-name-'+colour.key" :name="'draft_colours['+colour.key+'][name]'" x-model="colour.name" :aria-invalid="fieldError('draft_colours.'+colour.key+'.name') ? 'true' : 'false'">
                            <small class="admin-field-error" x-show="fieldError('draft_colours.'+colour.key+'.name')" x-text="fieldError('draft_colours.'+colour.key+'.name')"></small>
                        </div>
                        <div class="admin-field" :class="{'admin-field-invalid': fieldError('draft_colours.'+colour.key+'.swatch_hex')}">
                            <label :for="'draft-colour-swatch-'+colour.key">Swatch</label>
                            <div class="product-swatch-control"><input class="product-swatch-picker" type="color" :id="'draft-colour-swatch-'+colour.key" x-model="colour.swatch_hex" aria-label="Choose swatch colour"><input class="product-swatch-hex" :name="'draft_colours['+colour.key+'][swatch_hex]'" x-model="colour.swatch_hex" @blur="normaliseSwatch(colour)" placeholder="#808080" maxlength="7" spellcheck="false" :aria-invalid="fieldError('draft_colours.'+colour.key+'.swatch_hex') ? 'true' : 'false'"></div>
                            <small class="admin-field-error" x-show="fieldError('draft_colours.'+colour.key+'.swatch_hex')" x-text="fieldError('draft_colours.'+colour.key+'.swatch_hex')"></small>
                        </div>
                    </div>
                    <div class="admin-picker-selection">
                        <template x-for="(asset, mediaIndex) in colour.media" :key="asset.id">
                            <article class="admin-picker-selected-card">
                                <img :src="asset.thumbnail" :alt="asset.alt || ''"><div><strong x-text="asset.title"></strong><small x-text="asset.filename"></small></div>
                                <button type="button" class="admin-secondary-button" @click="colour.media.splice(mediaIndex, 1)">Remove</button>
                                <input type="hidden" :name="'draft_colours['+colour.key+'][media_ids][]'" :value="asset.id">
                                <input type="hidden" :name="'draft_colours['+colour.key+'][media_order]['+asset.id+']'" :value="mediaIndex">
                            </article>
                        </template>
                    </div>
                    <div class="admin-page-actions"><button type="button" class="admin-secondary-button" @click="openMedia(colour.key)">Choose images</button><button type="button" class="admin-text-button" @click="removeColour(index)">Remove colour</button></div>
                </article>
            </template>
        </div>
    </section>

    <section class="product-option-editor" aria-labelledby="draft-sizes-title">
        <div class="product-option-header"><h3 id="draft-sizes-title">Sizes</h3><button type="button" class="admin-secondary-button" @click="addSize()">Add size</button></div>
        <p class="admin-field-help" x-show="!sizes.length">No Sizes added. This is fine for a Colour-only or no-option Product.</p>
        <div class="product-size-rows">
            <template x-for="(size, index) in sizes" :key="size.key">
                <div class="product-size-row admin-field" :class="{'admin-field-invalid': fieldError('draft_sizes.'+size.key+'.name')}">
                    <label :for="'draft-size-'+size.key">Size</label><input :id="'draft-size-'+size.key" :name="'draft_sizes['+size.key+'][name]'" x-model="size.name" :aria-invalid="fieldError('draft_sizes.'+size.key+'.name') ? 'true' : 'false'">
                    <button type="button" class="admin-text-button" @click="removeSize(index)">Remove</button>
                </div>
            </template>
        </div>
    </section>

    <section class="product-option-editor" aria-labelledby="draft-variants-title">
        <div class="product-option-header"><div><h3 id="draft-variants-title">Variants</h3><p class="admin-field-help">A Variant is one sellable combination of this Product's options, such as Black / M.</p><strong class="product-variant-summary" x-text="variantSummary()"></strong><span class="admin-field-help" x-show="variants.length && defaultLabel()" x-text="'Default: '+defaultLabel()"></span></div><button type="button" class="admin-primary-button" @click="generateVariants()" x-text="variants.length ? 'Update Variants' : 'Generate Variants'">Generate Variants</button></div>
        <p class="admin-field-help" x-show="regenerationNotice" x-text="regenerationNotice" role="status"></p><p class="admin-field-help">Inactive Variants cannot be selected for purchase. New Product Variants are created Active.</p>
        <div class="admin-field-error" x-show="fieldError('draft_variants')" x-text="fieldError('draft_variants')"></div>
        <div class="admin-table-wrap product-variant-table-wrap" x-show="variants.length"><table class="product-variant-table"><thead><tr><th>Variant</th><th>SKU</th><th>Price</th><th>Status</th><th>Default</th></tr></thead><tbody>
            <template x-for="(variant, index) in variants" :key="variant.key"><tr>
                <td data-label="Variant"><strong x-text="variant.label"></strong><input type="hidden" :name="'draft_variants['+index+'][key]'" :value="variant.key"><input type="hidden" :name="'draft_variants['+index+'][label]'" :value="variant.label"><input type="hidden" :name="'draft_variants['+index+'][colour_key]'" :value="variant.colour_key || ''"><input type="hidden" :name="'draft_variants['+index+'][size_key]'" :value="variant.size_key || ''"></td>
                <td data-label="SKU" class="admin-field product-variant-sku" :class="{'admin-field-invalid': fieldError('draft_variants.'+index+'.sku')}"><input :name="'draft_variants['+index+'][sku]'" x-model="variant.sku" :title="variant.sku" aria-label="Variant SKU" :aria-invalid="fieldError('draft_variants.'+index+'.sku') ? 'true' : 'false'"><small class="admin-field-error" x-show="fieldError('draft_variants.'+index+'.sku')" x-text="fieldError('draft_variants.'+index+'.sku')"></small></td>
                <td data-label="Price" class="product-variant-price"><span x-show="!variant.override">Uses Product price</span><button type="button" class="admin-text-button" x-show="!variant.override" @click="variant.override=true">Set override</button><div x-show="variant.override"><label class="sr-only" :for="'draft-variant-price-'+index">Price override</label><input :id="'draft-variant-price-'+index" :name="'draft_variants['+index+'][price]'" x-model="variant.price" inputmode="numeric" placeholder="TZS override"><button type="button" class="admin-text-button" @click="variant.price=''; variant.override=false">Use Product price</button></div></td><td data-label="Status"><span class="admin-status">Active</span></td><td data-label="Default" class="product-variant-default"><label><input type="radio" name="default_variant_key" :value="variant.key" x-model="defaultKey"><span class="sr-only" x-text="'Make '+variant.label+' the default Variant'"></span></label></td>
            </tr></template>
        </tbody></table></div>
        <div class="admin-field-error" x-show="fieldError('default_variant_key')" x-text="fieldError('default_variant_key')"></div>
    </section>

    <dialog class="admin-media-picker-dialog" x-ref="dialog" @close="cancelMedia()">
        <div class="admin-picker-dialog-header"><div><p>Media Library</p><h2>Choose colour images</h2></div><button type="button" aria-label="Close media picker" @click="cancelMedia()">×</button></div>
        <div class="admin-field"><label for="draft-colour-media-search">Search</label><input id="draft-colour-media-search" type="search" x-model="search" @input.debounce.400ms="loadMedia(1)" placeholder="Filename or alt text"></div>
        <div class="admin-picker-toolbar"><span x-text="loading ? 'Loading…' : total+' ready images'"></span><button type="button" class="admin-secondary-button" @click="loadMedia(1)">Refresh media</button></div>
        <div class="admin-picker-results"><template x-for="asset in results" :key="asset.id"><button type="button" class="admin-picker-result" :class="{'is-selected': working.some(item => item.id === asset.id)}" @click="toggleMedia(asset)"><img :src="asset.thumbnail" alt="" loading="lazy"><strong x-text="asset.title"></strong><small x-text="asset.filename"></small></button></template></div>
        <div class="admin-picker-pagination"><button type="button" class="admin-secondary-button" @click="loadMedia(page-1)" :disabled="page <= 1">Previous</button><span x-text="'Page '+page+' of '+lastPage"></span><button type="button" class="admin-secondary-button" @click="loadMedia(page+1)" :disabled="page >= lastPage">Next</button></div>
        <div class="admin-page-actions"><button type="button" class="admin-secondary-button" @click="cancelMedia()">Cancel</button><button type="button" class="admin-primary-button" @click="commitMedia()">Use selected</button></div>
    </dialog>
</div>

@once
<script>
window.productDraftBuilder = (config) => ({
    colours: config.colours.map(item => ({...item, swatch_hex: item.swatch_hex || '#808080'})), sizes: config.sizes, variants: config.variants.map(item => ({...item, override: Boolean(item.price)})), defaultKey: config.defaultKey || '', errors: config.errors || {}, activeColourKey: null, regenerationNotice: '',
    working: [], results: [], search: '', loading: false, page: 1, lastPage: 1, total: 0,
    key(prefix) { return prefix+'-'+(crypto.randomUUID ? crypto.randomUUID() : Date.now()+'-'+Math.random().toString(16).slice(2)); },
    addColour() { this.colours.push({key: this.key('colour'), name: '', swatch_hex: '#808080', media: []}); },
    normaliseSwatch(colour) { const value = String(colour.swatch_hex || '').trim(); colour.swatch_hex = /^#[0-9a-f]{6}$/i.test(value) ? value.toUpperCase() : value; },
    removeColour(index) { this.colours.splice(index, 1); }, addSize() { this.sizes.push({key: this.key('size'), name: ''}); }, removeSize(index) { this.sizes.splice(index, 1); },
    slug(value) { return String(value || '').trim().toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-|-$/g, ''); },
    generateVariants() { const previous = Object.fromEntries(this.variants.map(item => [item.key, item])); const previousKeys = new Set(this.variants.map(item => item.key)); const colours = this.colours.filter(item => item.name.trim()); const sizes = this.sizes.filter(item => item.name.trim()); let combinations = [];
        if (colours.length && sizes.length) colours.forEach(colour => sizes.forEach(size => combinations.push([colour, size]))); else if (colours.length) combinations = colours.map(colour => [colour, null]); else if (sizes.length) combinations = sizes.map(size => [null, size]); else combinations = [[null, null]];
        const base = this.slug(document.getElementById('product-slug')?.value || document.getElementById('product-title')?.value || 'PRODUCT');
        this.variants = combinations.map(([colour, size]) => { const key = (colour?.key || 'none')+'--'+(size?.key || 'none'); const prior = previous[key]; const label = [colour?.name, size?.name].filter(Boolean).join(' / ') || 'Default'; return {key, colour_key: colour?.key || '', size_key: size?.key || '', label, sku: prior?.sku || ['WT', base, this.slug(colour?.name), this.slug(size?.name)].filter(Boolean).join('-').slice(0, 100), price: prior?.price || '', override: prior?.override || Boolean(prior?.price)}; });
        const retained = this.variants.filter(item => previousKeys.has(item.key)).length; const added = this.variants.length-retained; this.regenerationNotice = added+' new combination'+(added===1?'':'s')+' added. '+retained+' existing combination'+(retained===1?'':'s')+' retained with entered SKUs and prices.';
        if (!this.variants.some(item => item.key === this.defaultKey)) this.defaultKey = this.variants[0]?.key || '';
    },
    variantSummary() { if (!this.colours.length && !this.sizes.length) return '1 sellable Product Variant'; if (this.colours.length && this.sizes.length) return (this.variants.length || this.colours.length*this.sizes.length)+' sellable combinations · '+this.colours.length+' Colours × '+this.sizes.length+' Sizes'; if (this.colours.length) return this.colours.length+' sellable Colour'+(this.colours.length===1?'':'s'); return this.sizes.length+' sellable Size'+(this.sizes.length===1?'':'s'); },
    defaultLabel() { return this.variants.find(item => item.key === this.defaultKey)?.label || ''; },
    fieldError(key) { const value = this.errors[key]; return Array.isArray(value) ? value[0] : (value || ''); },
    openMedia(key) { this.activeColourKey = key; const colour = this.colours.find(item => item.key === key); this.working = (colour?.media || []).map(item => ({...item})); this.$refs.dialog.showModal(); this.loadMedia(1); },
    async loadMedia(page) { this.loading = true; const url = new URL(config.endpoint, window.location.origin); url.searchParams.set('page', page); if (this.search.trim()) url.searchParams.set('search', this.search.trim()); const response = await fetch(url, {headers: {'Accept':'application/json'}}); if (response.ok) { const payload = await response.json(); this.results = payload.data; this.page = payload.current_page; this.lastPage = payload.last_page; this.total = payload.total; } this.loading = false; },
    toggleMedia(asset) { this.working = this.working.some(item => item.id === asset.id) ? this.working.filter(item => item.id !== asset.id) : [...this.working, asset]; },
    commitMedia() { const colour = this.colours.find(item => item.key === this.activeColourKey); if (colour) colour.media = this.working.map(item => ({...item})); this.$refs.dialog.close('commit'); },
    cancelMedia() { if (this.$refs.dialog.open) this.$refs.dialog.close('cancel'); this.working = []; },
});
</script>
@endonce
