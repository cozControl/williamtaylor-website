@php
    $title = $resource->type === 'site_profile' ? 'Site settings' : $resource->title;
    $description = $resource->type === 'site_profile'
        ? "Manage your website's brand, contact details, social links and footer information."
        : 'Edit and save changes to the live storefront.';
@endphp

<x-admin.layout :title="$title" :description="$description" eyebrow="Website">
    <livewire:admin.site-content.workspace :site-content="$resource" />
</x-admin.layout>
