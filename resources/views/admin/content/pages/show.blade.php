<x-admin.layout :title="$page->title" description="Draft identity, immutable revision history, and reversible lifecycle." eyebrow="Content" :breadcrumbs="['Pages' => route('admin.content.pages.index'), $page->title => null]">
    <livewire:admin.content.pages.page-detail :page-id="$page->id" />
</x-admin.layout>
