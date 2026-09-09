@props(['title', 'description', 'eyebrow' => 'Administration', 'breadcrumbs' => []])
@php
    $navigationGroups = app(\App\Domain\Admin\Navigation\AdminNavigationRegistry::class)->groupedVisibleFor(auth()->user());
    $currentRoute = request()->route()?->getName();
    $environmentLabel = match (app()->environment()) {
        'production' => 'Production',
        'staging' => 'Staging',
        'testing' => 'Testing',
        default => 'Local',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} - Administration - {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    @fonts
    @vite(['resources/css/admin.css', 'resources/css/admin-access.css', 'resources/css/admin-media.css', 'resources/js/admin.js', 'resources/js/media-upload-queue.js', 'resources/js/media-replacement.js'])
    @if (request()->routeIs('admin.content.pages.*', 'admin.content.navigation.*', 'admin.content.announcements.*', 'admin.settings.*'))
        @vite(['resources/css/admin-cms.css', 'resources/js/cms-editor.js'])
    @endif
    @fluxAppearance
    <x-admin.form-styles />
</head>
<body class="admin-body" data-admin-ui-revision="ecom-home-2e">
    <a class="admin-skip-link" href="#admin-main">Skip to main content</a>
    <div class="admin-shell">
        <aside class="admin-sidebar" aria-label="Primary administration">
            <a href="{{ route('admin.dashboard') }}" class="admin-brand" aria-label="{{ config('app.name') }} administration home">
                <span class="admin-brand-mark">WT</span>
                <span><strong>{{ config('app.name') }}</strong><small>Administration</small></span>
            </a>
            <x-admin.navigation class="admin-sidebar-navigation" :groups="$navigationGroups" :current-route="$currentRoute" />
            <div class="admin-sidebar-footer">
                <span class="admin-environment">{{ $environmentLabel }}</span>
                <a href="{{ route('home') }}">View storefront</a>
            </div>
        </aside>
        <div class="admin-workspace">
            <header class="admin-topbar">
                <button type="button" class="admin-menu-button" data-admin-nav-open aria-controls="admin-navigation-dialog" aria-label="Open administration navigation"><span aria-hidden="true">Menu</span></button>
                <div class="admin-topbar-context"><span>{{ $eyebrow }}</span><strong>{{ $title }}</strong></div>
                <span class="admin-environment admin-environment-mobile">{{ $environmentLabel }}</span>
                <flux:dropdown position="bottom" align="end">
                    <flux:profile :name="auth()->user()->name" :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                    <flux:menu>
                        <div class="admin-user-summary"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></div>
                        <flux:menu.separator />
                        <flux:menu.item :href="route('profile.edit')" icon="user">Profile</flux:menu.item>
                        <flux:menu.item :href="route('security.edit')" icon="lock-closed">Security</flux:menu.item>
                        <flux:menu.item :href="route('appearance.edit')" icon="swatch">Appearance</flux:menu.item>
                        <flux:menu.separator />
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle">Log out</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            </header>
            <main id="admin-main" class="admin-main" tabindex="-1">
                @if (count($breadcrumbs))
                    <nav aria-label="Breadcrumb" class="admin-breadcrumbs"><ol>
                        @foreach ($breadcrumbs as $label => $url)
                            <li>@if ($url)<a href="{{ $url }}">{{ $label }}</a>@else<span aria-current="page">{{ $label }}</span>@endif</li>
                        @endforeach
                    </ol></nav>
                @endif
                <div class="admin-page-heading-row">
                    <header class="admin-page-header">
                        <p>{{ $eyebrow }}</p><h1>{{ $title }}</h1><span>{{ $description }}</span>
                    </header>
                    @isset($actions)
                        <div class="admin-page-heading-actions">{{ $actions }}</div>
                    @endisset
                </div>
                {{ $slot }}
            </main>
        </div>
    </div>
    <dialog id="admin-navigation-dialog" class="admin-drawer" aria-labelledby="admin-drawer-title">
        <div class="admin-drawer-header">
            <div><strong id="admin-drawer-title">{{ config('app.name') }}</strong><span>Administration</span></div>
            <button type="button" data-admin-nav-close aria-label="Close administration navigation">x</button>
        </div>
        <x-admin.navigation class="admin-drawer-navigation" :groups="$navigationGroups" :current-route="$currentRoute" />
        <div class="admin-drawer-footer"><span class="admin-environment">{{ $environmentLabel }}</span><a href="{{ route('home') }}">View storefront</a></div>
    </dialog>
    @fluxScripts
</body>
</html>
