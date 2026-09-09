<x-admin.layout title="Campaigns" eyebrow="Catalogue" description="Manage Pre-Order and Limited Edition Campaigns in one canonical workspace." :breadcrumbs="['Catalogue' => route('admin.catalogue.index'), 'Campaigns' => null]">
    <x-slot:actions>
        @can('products.manage')
            <a class="admin-primary-button" href="{{ route('admin.campaigns.create') }}">New Campaign</a>
        @endcan
    </x-slot:actions>

    @if($campaigns->isEmpty())
        <section class="admin-panel admin-empty-state">
            <h2>No Campaigns yet</h2>
            <p>Create a type-aware Campaign, then submit and approve its evidence-backed claims.</p>
            @can('products.manage')
                <a class="admin-primary-button" href="{{ route('admin.campaigns.create') }}">New Campaign</a>
            @endcan
        </section>
    @else
        <section class="admin-panel campaign-index-panel" aria-labelledby="campaign-list-title" data-campaign-index>
            <div class="campaign-index-summary">
                <div>
                    <p>Campaign library</p>
                    <h2 id="campaign-list-title">Current Campaigns</h2>
                </div>
                <p><strong>{{ $campaigns->count() }}</strong> {{ Str::plural('Campaign', $campaigns->count()) }}</p>
            </div>

            <div class="admin-table-wrap campaign-index-table">
                <table data-campaign-table>
                    <colgroup>
                        <col class="campaign-column">
                        <col class="campaign-product-column">
                        <col class="campaign-schedule-column">
                        <col class="campaign-status-column">
                        <col class="campaign-updated-column">
                        <col class="campaign-actions-column">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Campaign</th>
                            <th>Product</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $campaign)
                            @php
                                $result = $readiness->evaluate($campaign);
                                $target = $campaign->products->firstWhere('archived_at', null)?->product;
                                $state = $effectiveStates[$campaign->id];
                                $statusClass = match($state) {
                                    'Active' => 'active',
                                    'Ended', 'Archived' => 'archived',
                                    default => 'hidden',
                                };
                                $editUrl = route('admin.campaigns.edit', $campaign);
                            @endphp
                            <tr data-campaign-row>
                                <td data-label="Campaign">
                                    <div class="campaign-index-identity">
                                        <span class="campaign-type-badge is-{{ $campaign->campaign_type === 'pre_order' ? 'preorder' : 'limited' }}">{{ $typeLabels[$campaign->campaign_type] }}</span>
                                        @can('products.manage')
                                            <a href="{{ $editUrl }}"><strong>{{ $campaign->currentDraftRevision?->headline ?? 'Untitled Campaign' }}</strong></a>
                                        @else
                                            <strong>{{ $campaign->currentDraftRevision?->headline ?? 'Untitled Campaign' }}</strong>
                                        @endcan
                                        <small>{{ $campaign->internal_code }}</small>
                                    </div>
                                </td>
                                <td data-label="Product">
                                    @if($target)
                                        @can('products.manage')
                                            <a class="campaign-product-link" href="{{ route('admin.products.edit', $target) }}">{{ $target->currentDraftRevision?->title ?? 'Untitled Product' }}</a>
                                        @else
                                            <span>{{ $target->currentDraftRevision?->title ?? 'Untitled Product' }}</span>
                                        @endcan
                                    @else
                                        <span class="campaign-empty-value">No Product selected</span>
                                    @endif
                                </td>
                                <td data-label="Schedule">
                                    <div class="campaign-schedule">
                                        <div>
                                            <span>Starts</span>
                                            @if($campaign->starts_at)
                                                <time datetime="{{ $campaign->starts_at->toISOString() }}">{{ $campaign->starts_at->setTimezone('Africa/Nairobi')->format('d M Y') }}</time>
                                                <small>{{ $campaign->starts_at->setTimezone('Africa/Nairobi')->format('H:i') }} EAT</small>
                                            @else
                                                <span class="campaign-empty-value">Not set</span>
                                            @endif
                                        </div>
                                        <div>
                                            <span>Ends</span>
                                            @if($campaign->ends_at)
                                                <time datetime="{{ $campaign->ends_at->toISOString() }}">{{ $campaign->ends_at->setTimezone('Africa/Nairobi')->format('d M Y') }}</time>
                                                <small>{{ $campaign->ends_at->setTimezone('Africa/Nairobi')->format('H:i') }} EAT</small>
                                            @else
                                                <span class="campaign-empty-value">Not set</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Status">
                                    <div class="campaign-state" data-campaign-state>
                                        <span class="catalogue-status-badge is-{{ $statusClass }}">{{ $state }}</span>
                                        @if($result->ready)
                                            <small>Ready for its public window</small>
                                        @else
                                            <small>{{ count($result->failureCodes) }} {{ Str::plural('requirement', count($result->failureCodes)) }} unresolved</small>
                                        @endif
                                    </div>
                                </td>
                                <td data-label="Updated">
                                    <span class="campaign-updated">{{ $campaign->updated_at?->diffForHumans() ?? 'Unknown' }}</span>
                                </td>
                                <td data-label="Actions">
                                    <div class="campaign-row-actions">
                                        @can('products.manage')
                                            <a class="admin-secondary-button" href="{{ $editUrl }}">Edit</a>
                                        @else
                                            <span class="campaign-empty-value">Read only</span>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-admin.layout>
