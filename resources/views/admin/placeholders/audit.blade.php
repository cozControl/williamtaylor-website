<x-admin.layout title="Audit log" description="Reserved audit-review destination." :breadcrumbs="['Dashboard' => route('admin.dashboard'), 'Audit log' => null]">
    <x-admin.placeholder title="Audit review" purpose="This route confirms the approved audit.view access boundary without querying or displaying audit records." future="Read-only audit discovery and approved export behavior require a separately authorized phase." />
</x-admin.layout>
