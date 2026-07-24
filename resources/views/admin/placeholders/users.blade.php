<x-admin.layout title="Users" description="Reserved access-management destination." :breadcrumbs="['Dashboard' => route('admin.dashboard'), 'Users' => null]">
    <x-admin.placeholder title="User management" purpose="This route confirms the approved users.view access boundary without exposing user records or management controls." future="Search, review and explicit user access actions require separate BE-4C authorization." />
</x-admin.layout>
