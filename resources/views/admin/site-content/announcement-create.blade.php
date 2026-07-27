<x-admin.layout title="Create announcement" description="Create a governed announcement draft." eyebrow="Content">
    <section class="admin-panel">
        <form method="post" action="{{ route('admin.content.announcements.store') }}">
            @csrf
            <label>Internal title<input name="title" value="{{ old('title') }}" maxlength="160" required></label>
            @error('title')<p class="admin-error">{{ $message }}</p>@enderror
            <button class="admin-primary-button">Create governed announcement</button>
        </form>
    </section>
</x-admin.layout>
