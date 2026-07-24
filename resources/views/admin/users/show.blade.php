<x-admin.layout title="User access" description="Inspect effective permissions and manage existing registered role assignments." :breadcrumbs="['Dashboard' => route('admin.dashboard'), 'Users' => route('admin.access.users.index'), $user->name => null]">
    <livewire:admin.access.user-access-detail :user-id="$user->getKey()" />
</x-admin.layout>
