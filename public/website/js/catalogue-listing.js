(() => {
    const root = document.querySelector('[data-catalogue-listing]');
    if (!root) return;
    const panel = root.querySelector('#catalogue-filters');
    const toggle = root.querySelector('[data-catalogue-filter-toggle]');
    const backdrop = root.querySelector('[data-catalogue-filter-backdrop]');
    const close = root.querySelector('[data-catalogue-filter-close]');
    const mobile = matchMedia('(max-width:1023px)');
    const sync = () => {
        const modal = mobile.matches && !panel.hidden;
        backdrop.hidden = !modal;
        panel.setAttribute('role', modal ? 'dialog' : 'complementary');
        if (modal) panel.setAttribute('aria-modal', 'true'); else panel.removeAttribute('aria-modal');
        document.body.style.overflow = modal ? 'hidden' : '';
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
    };
    const setOpen = (open) => { panel.hidden = !open; sync(); if (mobile.matches && open) close.focus(); else if (!open) toggle.focus(); };
    toggle.addEventListener('click', () => setOpen(panel.hidden));
    close.addEventListener('click', () => setOpen(false));
    backdrop.addEventListener('click', () => setOpen(false));
    panel.addEventListener('keydown', event => {
        if (!mobile.matches || panel.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); setOpen(false); }
        if (event.key === 'Tab') {
            const controls = [...panel.querySelectorAll('a,button')].filter(el => el.getClientRects().length);
            const first = controls[0], last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    root.querySelector('[name="sort"]').addEventListener('change', event => event.target.form.requestSubmit());
    mobile.addEventListener('change', sync);
    // A selected filter stays visible on desktop; mobile returns to its results.
    if (mobile.matches) panel.hidden = true;
    sync();
    window.addEventListener('pageshow', sync);
})();
