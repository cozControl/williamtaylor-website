<?php

namespace App\Domain\Publication\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

final class PrivilegedRouteInventory
{
    /** @return list<array{name:?string,uri:string,methods:list<string>,classification:string,middleware:list<string>}> */
    public function all(): array
    {
        return array_values(collect(RouteFacade::getRoutes()->getRoutes())->map(function (Route $route): array {
            $middleware = $route->gatherMiddleware();
            $uri = $route->uri();
            $classification = match (true) {
                str_starts_with($uri, 'preview/') => 'signed_short_lived_preview_exception',
                str_starts_with($uri, 'admin') => collect($middleware)->contains(fn (string $item) => str_starts_with($item, 'can:') && $item !== 'can:admin.access') ? 'authenticated_permission_protected_administration' : 'authenticated_administration',
                in_array('auth', $middleware, true) => 'authenticated_administration',
                default => 'public_storefront_or_authentication',
            };

            return ['name' => $route->getName(), 'uri' => $uri, 'methods' => array_values(array_diff($route->methods(), ['HEAD'])), 'classification' => $classification, 'middleware' => array_values($middleware)];
        })->all());
    }
}
