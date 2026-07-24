<x-admin.layout title="Roles" description="Reserved role-management destination." :breadcrumbs="['Dashboard' => route('admin.dashboard'), 'Roles' => null]">
    <x-admin.placeholder title="Role management" purpose="This route confirms the approved roles.view access boundary without exposing role data or mutation controls." future="Role and permission management interfaces require separate BE-4C authorization." />
</x-admin.layout>
