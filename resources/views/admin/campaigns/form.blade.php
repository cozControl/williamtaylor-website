@php
    $revision = $campaign?->currentDraftRevision;
    $target = $campaign?->products?->firstWhere('archived_at', null);
    $windowClaim = $campaign?->claims?->firstWhere('claim_key', 'public_window_statement');
    $editionClaim = $campaign?->claims?->firstWhere('claim_key', 'edition_statement');
    $selectedProductId = (string) old('product_id', $target?->product_id);
@endphp
<x-admin.layout :title="$campaign ? 'Edit '.$typeLabel.' Campaign' : 'New '.$typeLabel.' Campaign'" eyebrow="Catalogue" :heading="$campaign ? ($revision?->headline ?? $typeLabel.' Campaign') : 'New '.$typeLabel.' Campaign'" :description="$campaignType === 'pre_order' ? 'Campaign messaging, Product, image, public window and estimated delivery.' : 'Campaign messaging, Product, image, fixed edition statement and public window.'">
    <x-admin.flash :errors="$errors" />
    <form method="POST" action="{{ $campaign ? route('admin.campaigns.update', $campaign) : route('admin.campaigns.store') }}" class="admin-form-stack">
        @csrf
        @if($campaign)
            @method('PUT')
        @endif
        <input type="hidden" name="campaign_type" value="{{ $campaignType }}">

        <section class="admin-form-section">
            <div class="admin-section-heading"><p>Campaign details</p><h2>{{ $typeLabel }}</h2></div>
            <p class="admin-field-help">Campaign type is fixed after creation.</p>
            <div class="admin-form-grid">
                <x-admin.field label="Internal code" for="campaign-code" :error="$errors->first('internal_code')">
                    <input id="campaign-code" name="internal_code" value="{{ old('internal_code', $campaign?->internal_code) }}" maxlength="100" @readonly($campaign) required>
                </x-admin.field>
                <x-admin.field label="Campaign headline" for="campaign-headline" :error="$errors->first('headline')">
                    <input id="campaign-headline" name="headline" value="{{ old('headline', $revision?->headline) }}" maxlength="255" required>
                </x-admin.field>
                <x-admin.field class="admin-form-span" label="Description" for="campaign-summary" :error="$errors->first('summary')">
                    <textarea id="campaign-summary" name="summary" maxlength="2000" required>{{ old('summary', $revision?->summary) }}</textarea>
                </x-admin.field>
                <x-admin.field :label="$campaignType === 'pre_order' ? 'Reserve button label' : 'Product button label'" for="campaign-cta" :error="$errors->first('cta_label')">
                    <input id="campaign-cta" name="cta_label" value="{{ old('cta_label', $revision?->cta_label ?? ($campaignType === 'pre_order' ? 'Reserve Yours' : 'View Piece')) }}" maxlength="80" required>
                </x-admin.field>
                <x-admin.field label="Product" for="campaign-product" :error="$errors->first('product_id')" help="A Campaign cannot make an incomplete Product public.">
                    <select id="campaign-product" name="product_id" required>
                        <option value="">Choose Product</option>
                        @foreach($products as $product)
                            <option value="{{ $product['id'] }}" @selected($selectedProductId === $product['id'])>{{ $product['title'] }} — {{ $product['ready'] ? 'Ready for storefront' : 'Needs attention: '.implode(', ', $product['reasons']) }}</option>
                        @endforeach
                    </select>
                </x-admin.field>
            </div>
        </section>

        <section class="admin-form-section">
            <div class="admin-section-heading"><p>Campaign image</p><h2>Storefront presentation</h2></div>
            <x-admin.media-picker id="campaign-media" name="media_asset_id" :selected="$selectedMedia" button-label="Choose Campaign image" change-label="Change Campaign image" :error="$errors->first('media_asset_id')" />
            <x-admin.field label="Alt text override (optional)" for="campaign-alt" :error="$errors->first('media_alt_override')" help="Leave blank to use the Media Library alt text.">
                <input id="campaign-alt" name="media_alt_override" value="{{ old('media_alt_override', $mediaAltOverride ?? null) }}" maxlength="320">
            </x-admin.field>
            <a class="admin-secondary-button" href="{{ route('admin.media.index') }}" target="_blank" rel="noopener">Open Media Library</a>
        </section>

        <section class="admin-form-section">
            <div class="admin-section-heading"><p>Public window</p><h2>Campaign schedule</h2></div>
            <div class="admin-form-grid">
                <x-admin.field label="Campaign starts" for="campaign-starts" :error="$errors->first('starts_at')">
                    <input id="campaign-starts" type="datetime-local" name="starts_at" value="{{ old('starts_at', $campaign?->starts_at?->setTimezone('Africa/Nairobi')->format('Y-m-d\TH:i')) }}" required>
                </x-admin.field>
                <x-admin.field :label="$campaignType === 'pre_order' ? 'Countdown ends' : 'Campaign ends'" for="campaign-ends" :error="$errors->first('ends_at')">
                    <input id="campaign-ends" type="datetime-local" name="ends_at" value="{{ old('ends_at', $campaign?->ends_at?->setTimezone('Africa/Nairobi')->format('Y-m-d\TH:i')) }}" required>
                </x-admin.field>
                @if($campaignType === 'pre_order')
                    <x-admin.field label="Estimated shipping date" for="campaign-delivery" :error="$errors->first('estimated_delivery_date')">
                        <input id="campaign-delivery" type="date" name="estimated_delivery_date" value="{{ old('estimated_delivery_date', $campaign?->estimated_delivery_date?->format('Y-m-d')) }}" required>
                    </x-admin.field>
                @endif
            </div>
        </section>

        @if($campaignType === 'limited_edition')
            <section class="admin-form-section">
                <div class="admin-section-heading"><p>Fixed edition claim</p><h2>Edition statement and evidence</h2></div>
                <p class="admin-field-help">Describe the total planned edition, such as “Only 30 Made.” This is not live stock or quantity remaining.</p>
                <div class="admin-form-grid">
                    <x-admin.field class="admin-form-span" label="Edition statement" for="campaign-edition" :error="$errors->first('edition_statement')">
                        <input id="campaign-edition" name="edition_statement" value="{{ old('edition_statement', $editionClaim?->normalized_value) }}" maxlength="120" required>
                    </x-admin.field>
                    <x-admin.field label="Edition evidence reference" for="campaign-edition-reference" :error="$errors->first('edition_evidence_reference')">
                        <input id="campaign-edition-reference" name="edition_evidence_reference" value="{{ old('edition_evidence_reference', $editionClaim?->evidence_reference) }}" maxlength="500" required>
                    </x-admin.field>
                    <x-admin.field label="Edition evidence summary" for="campaign-edition-summary" :error="$errors->first('edition_evidence_summary')">
                        <input id="campaign-edition-summary" name="edition_evidence_summary" value="{{ old('edition_evidence_summary', $editionClaim?->evidence_summary) }}" maxlength="500" required>
                    </x-admin.field>
                </div>
                <p>Approval status: {{ match($editionClaim?->approval_status) {'in_review' => 'Awaiting review', 'approved' => 'Approved', default => 'Not submitted'} }}</p>
            </section>
        @endif

        <section class="admin-form-section">
            <div class="admin-section-heading"><p>Public-window evidence</p><h2>Availability statement</h2></div>
            <div class="admin-form-grid">
                <x-admin.field class="admin-form-span" label="Public availability statement" for="campaign-window" :error="$errors->first('public_window_statement')">
                    <textarea id="campaign-window" name="public_window_statement" maxlength="300" required>{{ old('public_window_statement', $windowClaim?->normalized_value) }}</textarea>
                </x-admin.field>
                <x-admin.field label="Evidence reference" for="campaign-window-reference" :error="$errors->first('evidence_reference')">
                    <input id="campaign-window-reference" name="evidence_reference" value="{{ old('evidence_reference', $windowClaim?->evidence_reference) }}" maxlength="500" required>
                </x-admin.field>
                <x-admin.field label="Evidence summary" for="campaign-window-summary" :error="$errors->first('evidence_summary')">
                    <input id="campaign-window-summary" name="evidence_summary" value="{{ old('evidence_summary', $windowClaim?->evidence_summary) }}" maxlength="500" required>
                </x-admin.field>
            </div>
            <p>Approval status: {{ match($windowClaim?->approval_status) {'in_review' => 'Awaiting review', 'approved' => 'Approved', default => 'Not submitted'} }}</p>
        </section>

        <x-admin.form-actions>
            <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.campaigns.index') }}">Cancel</a></x-slot:secondary>
            <x-slot:primary><button class="admin-primary-button">Save and submit</button></x-slot:primary>
        </x-admin.form-actions>
    </form>

    @if($campaign && $campaign->claims->contains(fn($claim) => $claim->approval_status === 'in_review') && auth()->user()->can(\App\Domain\Identity\Support\PermissionRegistry::CAMPAIGN_CLAIMS_APPROVE))
        <form method="POST" action="{{ route('admin.campaigns.approve', $campaign) }}">
            @csrf
            <button class="admin-primary-button">Approve claims and publish</button>
        </form>
    @endif
</x-admin.layout>
