<x-admin.layout :title="'Edit '.$page->title" description="Update this informational page and save it to the storefront." eyebrow="Website" :breadcrumbs="['Pages' => route('admin.content.pages.index'), $page->title => null]">
    <livewire:admin.content.pages.page-editor :page-id="$page->id" />
</x-admin.layout>
