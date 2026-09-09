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
    @elseif ($name === 'catalogue')
        <path d="m4 7 8-4 8 4-8 4-8-4Zm0 5 8 4 8-4M4 17l8 4 8-4" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @elseif ($name === 'products')
        <path d="M4 7.5 12 3l8 4.5V17l-8 4-8-4V7.5Zm0 0 8 4 8-4M12 11.5V21" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @elseif ($name === 'categories')
        <path d="M3 5h7l2 2h9v12H3V5Zm9 6h6M12 15h4" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @elseif ($name === 'collections')
        <path d="m12 3 9 5-9 5-9-5 9-5Zm-9 10 9 5 9-5M3 17l9 5 9-5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @elseif ($name === 'campaigns')
        <path d="M4 12V6h7l2-2h7v12h-7l-2 2H4v-6Zm4-3h8M8 13h5" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @elseif ($name === 'home')
        <path d="m3 11 9-8 9 8v10h-6v-6H9v6H3V11Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @elseif ($name === 'settings')
        <path d="M4 7h10M18 7h2M4 17h2M10 17h10M14 4v6M6 14v6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    @elseif ($name === 'pages')
        <path d="M6 3h8l4 4v14H6V3Zm8 0v5h4M9 12h6M9 16h6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @elseif ($name === 'navigation')
        <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
    @elseif ($name === 'announcements')
        <path d="M4 11v3h3l2 5h3l-2-5 8 3V7L7 11H4Zm14-1c2 1 2 3 0 4" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @elseif ($name === 'media')
        <path d="M3 5h18v14H3V5Zm3 11 4-4 3 3 2-2 3 3M16 9h.01" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @elseif ($name === 'orders')
        <path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6M9 12h6" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
    @else
        <path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Zm7.4-3.5c0-.5-.04-.97-.13-1.43l2.06-1.61-2-3.46-2.5 1A8.2 8.2 0 0 0 14.36 5L14 2.33h-4L9.64 5a8.2 8.2 0 0 0-2.47 1.5l-2.5-1-2 3.46 2.06 1.61a7.7 7.7 0 0 0 0 2.86l-2.06 1.61 2 3.46 2.5-1A8.2 8.2 0 0 0 9.64 19l.36 2.67h4l.36-2.67a8.2 8.2 0 0 0 2.47-1.5l2.5 1 2-3.46-2.06-1.61c.09-.46.13-.94.13-1.43Z" stroke="currentColor" stroke-width="1.5"/>
    @endif
</svg>
