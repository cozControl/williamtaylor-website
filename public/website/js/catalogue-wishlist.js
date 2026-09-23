(() => {
    const key = 'wt_catalogue_wishlist';
    const saved = () => { try { const value = JSON.parse(localStorage.getItem(key) || '[]'); return Array.isArray(value) ? value.filter(s => typeof s === 'string' && s.length <= 160).slice(0, 100) : []; } catch { return []; } };
    const sync = () => {
        const selected = saved();
        for (const card of document.querySelectorAll('[data-storefront-product-card][data-product-slug]')) {
            const button = card.querySelector('button');
            if (!button) continue;
            const active = selected.includes(card.dataset.productSlug);
            button.setAttribute('aria-pressed', String(active));
            button.setAttribute('aria-label', active ? 'Remove from wishlist' : 'Add to wishlist');
            button.querySelector('svg')?.setAttribute('fill', active ? 'currentColor' : 'none');
        }
    };
    let requestNumber = 0;
    const refresh = async () => {
        const target = document.querySelector('[data-canonical-wishlist]');
        if (!target) return sync();
        const current = ++requestNumber;
        const url = new URL(target.dataset.url, location.origin);
        url.searchParams.set('fragment', '1');
        for (const slug of saved()) url.searchParams.append('items[]', slug);
        target.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, { headers: { Accept: 'text/html' } });
            if (!response.ok) throw new Error('Wishlist unavailable');
            const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
            const content = documentFragment.querySelector('[data-wishlist-items]');
            if (current !== requestNumber || !content) return;
            target.replaceChildren(content);
            const count = document.querySelector('[data-wishlist-count]');
            if (count) count.textContent = `${content.dataset.count} saved pieces`;
        } catch {
            if (current === requestNumber) target.setAttribute('aria-label', 'Saved pieces could not be loaded. Refresh to try again.');
        } finally {
            if (current === requestNumber) target.removeAttribute('aria-busy');
            sync();
        }
    };
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-storefront-product-card][data-product-slug] button');
        if (!button) return;
        const slug = button.closest('[data-product-slug]').dataset.productSlug;
        const previous = saved();
        const next = previous.includes(slug) ? previous.filter(s => s !== slug) : [...previous, slug].slice(-100);
        try { localStorage.setItem(key, JSON.stringify(next)); } catch { return; }
        sync(); refresh();
    });
    window.addEventListener('storage', event => { if (event.key === key) refresh(); });
    window.addEventListener('pageshow', refresh);
    sync();
})();
