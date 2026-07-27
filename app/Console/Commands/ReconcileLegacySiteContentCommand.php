<?php

namespace App\Console\Commands;

use App\Domain\SiteContent\Actions\ReconcileLegacyGlobalSiteContent;
use Illuminate\Console\Command;

final class ReconcileLegacySiteContentCommand extends Command
{
    protected $signature = 'site-content:reconcile-legacy-global';

    protected $description = 'Idempotently split a legacy global Site Content payload into governed typed resources.';

    public function handle(ReconcileLegacyGlobalSiteContent $reconcile): int
    {
        $resources = $reconcile->handle();
        $this->info($resources === [] ? 'No legacy global Site Content requires reconciliation.' : 'Reconciled '.count($resources).' typed resources.');

        return self::SUCCESS;
    }
}
