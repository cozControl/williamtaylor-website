@props(['name'])
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'aria-hidden' => 'true']) }}>
    @if ($name === 'dashboard')
        <path d="M4 13h6V4H4v9Zm0 7h6v-4H4v4Zm10 0h6v-9h-6v9Zm0-16v4h6V4h-6Z" stroke="currentColor" stroke-width="1.5"/>
    @elseif ($name === 'users')
        <path d="M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-5A4.5 4.5 0 0 0 2 18.5V20m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8 1a3.5 3.5 0 0 0 0-7m5 16v-1.5a4.5 4.5 0 0 0-3-4.24" stroke="currentColor" stroke-width="1.5"/>
    @elseif ($name === 'roles')
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-3-10 2 2 4-5" stroke="currentColor" stroke-width="1.5"/>
    @elseif ($name === 'audit')
        <path d="M9 5h10M9 9h10M9 13h6M5 5h.01M5 9h.01M5 13h.01M4 21h12a4 4 0 0 0 4-4V3" stroke="currentColor" stroke-width="1.5"/>
    @else
        <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Zm7.4-3.5c0-.5-.04-.97-.13-1.43l2.06-1.61-2-3.46-2.5 1A8.2 8.2 0 0 0 14.36 5L14 2.33h-4L9.64 5a8.2 8.2 0 0 0-2.47 1.5l-2.5-1-2 3.46 2.06 1.61a7.7 7.7 0 0 0 0 2.86l-2.06 1.61 2 3.46 2.5-1A8.2 8.2 0 0 0 9.64 19l.36 2.67h4l.36-2.67a8.2 8.2 0 0 0 2.47-1.5l2.5 1 2-3.46-2.06-1.61c.09-.46.13-.94.13-1.43Z" stroke="currentColor" stroke-width="1.5"/>
    @endif
</svg>
