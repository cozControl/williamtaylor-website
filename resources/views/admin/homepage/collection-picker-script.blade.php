    <script>
    (() => {
     const form = document.querySelector('.homepage-explore-collections-editor');
     if (!form) return;
     const dialog = form.querySelector('[data-collection-picker]');
     const results = form.querySelector('[data-collection-results]');
     const search = form.querySelector('[data-collection-search]');
     const feedback = form.querySelector('[data-picker-feedback]');
     const pageLabel = form.querySelector('[data-collection-page]');
     const previous = form.querySelector('[data-collection-previous]');
     const next = form.querySelector('[data-collection-next]');
     let activeSlot = null;
     let page = 1;
     let lastPage = 1;
     let timer;
     const escape = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
     const chosenIds = () => [...form.querySelectorAll('[data-collection-input]')].map(input => input.value).filter(Boolean);
     const load = async requestedPage => {
      page = Math.max(1, requestedPage);
      feedback.textContent = '';
      results.innerHTML = '<p>Loading Collections&hellip;</p>';
      const url = new URL(form.dataset.collectionPickerEndpoint, window.location.origin);
      url.searchParams.set('search', search.value.trim());
      url.searchParams.set('page', String(page));
      try {
       const response = await fetch(url, {headers: {'Accept': 'application/json'}});
       if (!response.ok) throw new Error('Collection search failed.');
       const payload = await response.json();
       page = payload.current_page;
       lastPage = Math.max(1, payload.last_page);
       pageLabel.textContent = `Page ${page} of ${lastPage}`;
       previous.disabled = page <= 1;
       next.disabled = page >= lastPage;
       results.innerHTML = payload.data.length ? payload.data.map(collection => `<button type="button" class="homepage-collection-result" data-id="${escape(collection.id)}" data-name="${escape(collection.name)}" data-slug="${escape(collection.slug)}" data-visibility="${escape(collection.visibility)}" data-status="${escape(collection.status)}" data-total="${collection.product_count}" data-ready="${collection.ready_count}" data-image="${escape(collection.image)}">${collection.image ? `<img src="${escape(collection.image)}" alt="">` : '<span class="admin-image-empty">No image</span>'}<strong>${escape(collection.name)}</strong><span>/${escape(collection.slug)}</span><span>${collection.product_count} Products &middot; ${collection.ready_count} ready</span><small>${escape(collection.visibility)} &middot; ${escape(collection.status)}</small></button>`).join('') : '<p class="admin-picker-empty">No eligible Collections found.</p>';
      } catch (error) {
       results.innerHTML = '<p class="admin-picker-empty">Collections could not be loaded. Try again.</p>';
      }
     };
     const renderSlot = (slot, collection) => {
      slot.querySelector('[data-collection-input]').value = collection?.id ?? '';
      slot.querySelector('[data-collection-visibility]').textContent = collection?.visibility ?? 'Not selected';
      slot.querySelector('[data-collection-summary]').hidden = !collection;
      slot.querySelector('[data-collection-empty]').hidden = Boolean(collection);
      slot.querySelector('[data-remove-collection]').hidden = !collection;
      slot.querySelector('[data-open-collection-picker]').textContent = collection ? 'Change Collection' : 'Choose Collection';
      if (!collection) return;
      const summary = slot.querySelector('[data-collection-summary]');
      let image = summary.querySelector('[data-collection-image]');
      const emptyImage = summary.querySelector('[data-collection-image-empty]');
      if (collection.image) {
       if (!image) {
        image = document.createElement('img');
        image.dataset.collectionImage = '';
        image.alt = '';
        (emptyImage ?? summary.firstElementChild)?.replaceWith(image);
       }
       image.src = collection.image;
      }
      summary.querySelector('[data-collection-name]').textContent = collection.name;
      summary.querySelector('[data-collection-slug]').textContent = `/collections/${collection.slug}`;
      summary.querySelector('[data-collection-counts]').textContent = `${collection.total} Products · ${collection.ready} ready for storefront`;
      summary.querySelector('[data-collection-status]').textContent = collection.status;
     };
     form.querySelectorAll('[data-open-collection-picker]').forEach(button => button.addEventListener('click', () => {
      activeSlot = button.closest('[data-collection-slot]');
      dialog.showModal();
      search.focus();
      load(1);
     }));
     form.querySelectorAll('[data-remove-collection]').forEach(button => button.addEventListener('click', () => renderSlot(button.closest('[data-collection-slot]'), null)));
     form.querySelector('[data-close-collection-picker]').addEventListener('click', () => dialog.close());
     search.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(1), 250); });
     previous.addEventListener('click', () => load(page - 1));
     next.addEventListener('click', () => load(page + 1));
     results.addEventListener('click', event => {
      const choice = event.target.closest('[data-id]');
      if (!choice || !activeSlot) return;
      const currentId = activeSlot.querySelector('[data-collection-input]').value;
      if (choice.dataset.id !== currentId && chosenIds().includes(choice.dataset.id)) {
       feedback.textContent = 'That Collection is already used in another position.';
       return;
      }
      renderSlot(activeSlot, {id: choice.dataset.id, name: choice.dataset.name, slug: choice.dataset.slug, visibility: choice.dataset.visibility, status: choice.dataset.status, total: Number(choice.dataset.total), ready: Number(choice.dataset.ready), image: choice.dataset.image});
      dialog.close();
     });
    })();
    </script>
