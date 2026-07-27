<?php

namespace App\Console\Commands;

use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;
use Illuminate\Console\Command;

final class WarmPublicSiteContentCommand extends Command
{
    protected $signature = 'public-site-content:warm';

    protected $description = 'Build enabled public Site Content projection cache entries.';

    public function handle(ResolvePublicSiteChrome $resolver): int
    {
        if (! config('public_site_content.enabled')) {
            $this->components->info('Projection is disabled; no public Site Content cache was warmed.');

            return self::SUCCESS;
        }

        $chrome = $resolver->resolve();
        if ($chrome->navigation === null || $chrome->footerGroups === null || $chrome->profile === null) {
            $this->components->error('Public Site Content cache warming failed readiness checks.');

            return self::FAILURE;
        }

        $this->components->info('Public Site Content projection cache warmed.');

        return self::SUCCESS;
    }
}
