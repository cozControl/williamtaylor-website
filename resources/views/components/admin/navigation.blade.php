@props(['groups', 'currentRoute' => null])
<nav {{ $attributes->merge(['aria-label' => 'Administration']) }}>
    @foreach ($groups as $group => $items)
        @php($groupActive = collect($items)->contains(fn ($item) => $item->isActive($currentRoute)) || ($group === 'Catalogue' && request()->routeIs('admin.catalogue.*')))
        <section class="admin-nav-group" @if($groupActive) data-group-active="true" @endif aria-labelledby="admin-nav-{{ str($group)->slug() }}">
            <h2 id="admin-nav-{{ str($group)->slug() }}" class="admin-nav-heading">{{ $group }}</h2>
            <ul role="list">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ route($item->routeName) }}" @if ($item->isActive($currentRoute)) aria-current="page" data-admin-active-nav-link @endif class="admin-nav-link">
                            <x-admin.icon :name="$item->icon" class="admin-nav-icon" />
                            <span>{{ $item->label }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</nav>
