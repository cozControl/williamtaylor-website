<x-admin.layout title="Settings" description="Reserved system-settings destination." :breadcrumbs="['Dashboard' => route('admin.dashboard'), 'Settings' => null]">
    <x-admin.placeholder title="System settings" purpose="This route confirms the approved settings.view access boundary without exposing configuration forms." future="Typed settings and explicit update actions require a separately authorized phase." />
</x-admin.layout>
