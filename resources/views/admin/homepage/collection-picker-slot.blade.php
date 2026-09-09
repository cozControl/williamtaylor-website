                    <article class="homepage-explore-collection-slot @if($fieldError) admin-field-invalid @endif" data-collection-slot="{{ $position }}">
                        <input type="hidden" name="{{ $field }}" value="{{ $selected['id'] ?? '' }}" data-collection-input>
                        <div class="homepage-explore-collection-slot-heading">
                            <span class="admin-status-label">Position {{ $position }}</span>
                            <span data-collection-visibility>{{ $selected['visibility'] ?? 'Not selected' }}</span>
                        </div>
                        <div class="homepage-explore-collection-summary" data-collection-summary @if(!$selected) hidden @endif>
                            @if($selectedImage)
                                <img src="{{ $selectedImage }}" alt="" data-collection-image>
                            @else
                                <span class="admin-image-empty" data-collection-image-empty>No image</span>
                            @endif
                            <div>
                                <strong data-collection-name>{{ $selected['title'] ?? 'No Collection selected' }}</strong>
                                <small data-collection-slug>{{ filled($selected['slug'] ?? null) ? '/collections/'.$selected['slug'] : 'Choose a public Collection' }}</small>
                                <span data-collection-counts>{{ $selected ? $selected['product_count'].' Products · '.$selected['ready_count'].' ready for storefront' : '' }}</span>
                                <span data-collection-status>{{ $selected['status'] ?? '' }}</span>
                            </div>
                        </div>
                        <p class="homepage-explore-collection-empty" data-collection-empty @if($selected) hidden @endif>No Collection selected for this position.</p>
                        @if($fieldError)
                            <p class="admin-field-error" role="alert">{{ $fieldError }}</p>
                        @endif
                        <div class="admin-page-actions">
                            <button class="admin-secondary-button" type="button" data-open-collection-picker>{{ $selected ? 'Change Collection' : 'Choose Collection' }}</button>
                            <button class="admin-text-button" type="button" data-remove-collection @if(!$selected) hidden @endif>Remove</button>
                        </div>
                    </article>
