<x-admin.layout :title="$resource->title" description="Govern typed draft, review, schedule and publication state." :eyebrow="$resource->type === 'site_profile' ? 'System' : 'Content'">
    <livewire:admin.site-content.workspace :site-content="$resource" />
</x-admin.layout>
