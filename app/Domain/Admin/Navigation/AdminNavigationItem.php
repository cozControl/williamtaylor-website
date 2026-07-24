<?php

namespace App\Domain\Admin\Navigation;

final readonly class AdminNavigationItem
{
    public function __construct(
        public string $key,
        public string $label,
        public string $routeName,
        public string $permission,
        public string $group,
        public int $groupOrder,
        public int $order,
        public string $description,
        public string $icon,
        public string $activePattern,
    ) {}

    public function isActive(?string $routeName): bool
    {
        return $routeName !== null
            && ($routeName === $this->routeName || str($routeName)->is($this->activePattern));
    }
}
