        <dialog class="homepage-collection-picker" data-collection-picker>
            <div class="admin-picker-dialog-header">
                <div><p>Collections</p><h2>Choose Collection</h2></div>
                <button type="button" aria-label="Close Collection picker" data-close-collection-picker>&times;</button>
            </div>
            <label class="admin-field">
                <span class="admin-field-label">Search Collections</span>
                <input type="search" data-collection-search placeholder="Name or slug">
            </label>
            <p class="admin-field-help" data-picker-feedback aria-live="polite"></p>
            <div class="homepage-collection-results" data-collection-results><p>Search or browse eligible Collections.</p></div>
            <div class="admin-picker-pagination">
                <button type="button" class="admin-secondary-button" data-collection-previous>Previous</button>
                <span data-collection-page>Page 1 of 1</span>
                <button type="button" class="admin-secondary-button" data-collection-next>Next</button>
            </div>
        </dialog>
