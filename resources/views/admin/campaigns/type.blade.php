<x-admin.layout title="Choose Campaign type" eyebrow="Catalogue" description="Start a type-aware Campaign without mixing unrelated fields.">
    <div class="admin-form-grid">
        @foreach($types as $type)
            <a class="admin-panel" href="{{ route('admin.campaigns.create', ['type' => $type['key']]) }}">
                <span class="admin-status-label">{{ $type['label'] }}</span>
                <h2>{{ $type['label'] }} Campaign</h2>
                <p>{{ $type['key'] === 'pre_order' ? 'Manage advance-release presentation and estimated delivery.' : 'Manage a fixed, evidence-backed edition statement without implying live stock.' }}</p>
            </a>
        @endforeach
    </div>
    <x-admin.form-actions>
        <x-slot:secondary><a class="admin-secondary-button" href="{{ route('admin.campaigns.index') }}">Back</a></x-slot:secondary>
        <x-slot:primary></x-slot:primary>
    </x-admin.form-actions>
</x-admin.layout>
