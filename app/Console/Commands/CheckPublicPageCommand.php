<?php

namespace App\Console\Commands;

use App\Domain\PublicProjection\Registry\PublicPageRouteRegistry;
use App\Domain\PublicProjection\Services\ResolvePublicPage;
use Illuminate\Console\Command;

final class CheckPublicPageCommand extends Command
{
    protected $signature = 'public-page:check {key}';

    protected $description = 'Verify a code-owned public Page projection without exposing content.';

    public function handle(PublicPageRouteRegistry $routes, ResolvePublicPage $resolver): int
    {
        try {
            $entry = $routes->get((string) $this->argument('key'));
        } catch (\Throwable) {
            $this->components->error('Public Page route is not registered.');

            return self::FAILURE;
        } $this->line('Feature flag: '.(config('public_page_projection.enabled') ? 'enabled' : 'disabled'));
        $this->line('Route: '.$entry['path']);
        $this->line('Static fallback: '.$entry['fallback']);
        if (! config('public_page_projection.enabled')) {
            $this->components->info('Static fallback is active.');

            return self::SUCCESS;
        } if (! $resolver->resolve((string) $this->argument('key'))) {
            $this->components->error('Enabled public Page is not projection-ready.');

            return self::FAILURE;
        } $this->components->info('Public Page is projection-ready.');

        return self::SUCCESS;
    }
}
