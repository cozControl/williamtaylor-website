<x-admin.layout :title="$page->title" description="Draft content, version history, review and publishing." eyebrow="Website" :breadcrumbs="['Pages' => route('admin.content.pages.index'), $page->title => null]">
    <livewire:admin.content.pages.page-detail :page-id="$page->id" />
</x-admin.layout>
