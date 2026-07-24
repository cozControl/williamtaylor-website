<?php

namespace App\Console\Commands;

use App\Domain\Identity\Actions\AlignFoundationRegistry;
use App\Domain\Identity\Exceptions\UnexpectedFoundationAssignmentException;
use App\Domain\Identity\Support\FoundationRegistryAlignmentPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class AlignFoundationRegistryCommand extends Command
{
    protected $signature = 'rbac:align-foundation-registry
        {--apply : Apply the reviewed alignment; default is preview only}
        {--force : Required with --apply in production}
        {--reason=BE-4A.1 authorized foundation registry correction : Append-only audit reason}';

    protected $description = 'Preview or apply the approved BE-4A foundation permission registry alignment';

    public function handle(AlignFoundationRegistry $alignment): int
    {
        if ($this->option('apply') && app()->isProduction() && ! $this->option('force')) {
            $this->error('Production alignment requires both --apply and --force.');

            return self::FAILURE;
        }

        $plan = $alignment->inspect();
        $this->displayPlan($plan);

        if ($plan->hasUnexpectedAssignments()) {
            $this->error('Alignment is blocked by unexpected assignments. No mutation was performed.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info('Preview complete. No mutation was performed; rerun with --apply after review.');

            return self::SUCCESS;
        }

        try {
            $result = $alignment->apply(trim((string) $this->option('reason')));
        } catch (UnexpectedFoundationAssignmentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $result->applied) {
            $this->info('Foundation registry is already aligned; no mutation or duplicate audit event was created.');
        } else {
            $this->info('Foundation registry alignment was applied and audited.');
        }

        $auditExit = Artisan::call('rbac:audit');
        $this->output->write(Artisan::output());

        return $auditExit === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }

    private function displayPlan(FoundationRegistryAlignmentPlan $plan): void
    {
        $this->line('Additions: '.($plan->additions === [] ? '(none)' : implode(', ', $plan->additions)));
        $this->line('Removals: '.($plan->removals === [] ? '(none)' : implode(', ', $plan->removals)));

        if ($plan->roleBundleChanges === []) {
            $this->line('Role bundle changes: (none)');
        } else {
            foreach ($plan->roleBundleChanges as $role => $change) {
                $this->line("Role [{$role}] before: ".implode(', ', $change['before']));
                $this->line("Role [{$role}] after: ".implode(', ', $change['after']));
            }
        }

        $this->line('CMS Manager user identifiers: '.(
            $plan->cmsManagerUserIdentifiers === [] ? '(none)' : implode(', ', $plan->cmsManagerUserIdentifiers)
        ));

        foreach ($plan->directAssignments as $assignment) {
            $this->error(
                "Unexpected direct assignment: {$assignment['permission']} on "
                ."{$assignment['model_type']}:{$assignment['model_identifier']}",
            );
        }

        foreach ($plan->unexpectedRoleAssignments as $assignment) {
            $this->error("Unexpected role assignment: {$assignment['permission']} on role {$assignment['role']}");
        }
    }
}
