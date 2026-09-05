@props([
    'label',
    'for',
    'help' => null,
    'error' => null,
])

@php
    $helpId = $help ? $for.'-help' : null;
    $errorId = $error ? $for.'-error' : null;
@endphp

<div {{ $attributes->class(['admin-field', 'admin-field-invalid' => $error]) }}>
    <label for="{{ $for }}">{{ $label }}</label>
    {{ $slot }}
    @if ($help)
        <small id="{{ $helpId }}" class="admin-field-help">{{ $help }}</small>
    @endif
    @if ($error)
        <small id="{{ $errorId }}" class="admin-field-error" role="alert">{{ $error }}</small>
    @endif
</div>
