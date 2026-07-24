<x-admin.layout :title="'Edit '.$page->title" description="Save validated sections as immutable draft revisions." eyebrow="Content" :breadcrumbs="['Pages' => route('admin.content.pages.index'), $page->title => route('admin.content.pages.show', $page), 'Edit' => null]">
    <livewire:admin.content.pages.page-editor :page-id="$page->id" />
</x-admin.layout>
