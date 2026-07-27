<?php

namespace App\Console\Commands;

use App\Domain\PublicProjection\Services\ResolvePublicSiteChrome;
use Illuminate\Console\Command;

final class CheckPublicSiteContentCommand extends Command
{
    protected $signature = 'public-site-content:check';

    protected $description = 'Verify the configured public Site Content projection without exposing payloads.';

    public function handle(ResolvePublicSiteChrome $resolver): int
    {
        if (! config('public_site_content.enabled')) {
            $this->components->info('Public Site Content projection is disabled; static fallback is active.');

            return self::SUCCESS;
        }

        $chrome = $resolver->resolve();
        if ($chrome->navigation === null || $chrome->footerGroups === null || $chrome->profile === null) {
            $this->components->error('Required public Site Content surfaces are not projection-ready.');

            return self::FAILURE;
        }

        $this->components->info('Required public Site Content surfaces are projection-ready.');

        return self::SUCCESS;
    }
}
