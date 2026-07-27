<?php

namespace App\Console\Commands;

use App\Domain\PublicProjection\Registry\PublicPageRouteRegistry;
use App\Domain\PublicProjection\Services\ResolvePublicPage;
use Illuminate\Console\Command;

final class WarmPublicPageCommand extends Command
{
    protected $signature = 'public-page:warm {key}';

    protected $description = 'Build the enabled public Page projection cache.';

    public function handle(PublicPageRouteRegistry $routes, ResolvePublicPage $resolver): int
    {
        try {
            $routes->get((string) $this->argument('key'));
        } catch (\Throwable) {
            $this->components->error('Public Page route is not registered.');

            return self::FAILURE;
        } if (! config('public_page_projection.enabled')) {
            $this->components->info('Projection is disabled; no public Page cache was warmed.');

            return self::SUCCESS;
        } if (! $resolver->resolve((string) $this->argument('key'))) {
            $this->components->error('Public Page cache warming failed readiness checks.');

            return self::FAILURE;
        } $this->components->info('Public Page projection cache warmed.');

        return self::SUCCESS;
    }
}
