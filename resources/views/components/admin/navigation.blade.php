@props(['groups', 'currentRoute' => null])
<nav {{ $attributes->merge(['aria-label' => 'Administration']) }}>
    @foreach ($groups as $group => $items)
        <section class="admin-nav-group" aria-labelledby="admin-nav-{{ str($group)->slug() }}">
            <h2 id="admin-nav-{{ str($group)->slug() }}" class="admin-nav-heading">{{ $group }}</h2>
            <ul role="list">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ route($item->routeName) }}" @if ($item->isActive($currentRoute)) aria-current="page" @endif class="admin-nav-link">
                            <x-admin.icon :name="$item->icon" class="admin-nav-icon" />
                            <span>{{ $item->label }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</nav>
