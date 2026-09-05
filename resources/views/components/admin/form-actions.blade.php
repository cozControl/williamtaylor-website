@props(['sticky' => false])

<div {{ $attributes->class(['admin-form-actions', 'is-sticky' => $sticky]) }} data-admin-form-actions>
    <div class="admin-form-actions-secondary">
        {{ $secondary }}
    </div>
    <div class="admin-form-actions-primary">
        {{ $primary }}
    </div>
</div>
