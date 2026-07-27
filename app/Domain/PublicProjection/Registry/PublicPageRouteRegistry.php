<?php

namespace App\Domain\PublicProjection\Registry;

use InvalidArgumentException;

final class PublicPageRouteRegistry
{
    /** @return array<string, array{route:string,path:string,page_key:string,type:string,template:string,fallback:string,renderer:string}> */
    public function all(): array
    {
        return ['about' => ['route' => 'about', 'path' => '/about', 'page_key' => 'about', 'type' => 'standard', 'template' => 'about', 'fallback' => 'frontend.about-static', 'renderer' => 'frontend.about-projected']];
    }

    /** @return array{route:string,path:string,page_key:string,type:string,template:string,fallback:string,renderer:string} */
    public function get(string $key): array
    {
        return $this->all()[$key] ?? throw new InvalidArgumentException('Unknown public Page route.');
    }

    public function checksum(): string
    {
        return hash('sha256', json_encode($this->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
