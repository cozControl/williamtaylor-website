<?php

namespace App\Console\Commands;

use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Snippe\StartSnippePayment;
use Illuminate\Console\Command;

final class ReconcileSnippePayments extends Command
{
    protected $signature = 'payments:reconcile-snippe {--limit=10}';

    protected $description = 'Reconcile a bounded batch of outstanding Snippe Sessions without exposing customer payloads';

    public function handle(StartSnippePayment $service): int
    {
        if (! config('snippe.enabled')) {
            $this->info('Snippe is disabled.');

            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 50) {
            $this->error('Use --limit between 1 and 50.');

            return self::FAILURE;
        }
        $deadline = microtime(true) + 45;
        $count = 0;
        $failed = 0;
        $payments = Payment::query()->whereNotNull('active_order_id')->where(fn ($query) => $query->whereNull('next_reconcile_at')->orWhere('next_reconcile_at', '<=', now('UTC')))->where(fn ($query) => $query->whereNull('io_lease_until')->orWhere('io_lease_until', '<=', now('UTC')))->orderBy('next_reconcile_at')->orderBy('id')->limit($limit)->get();
        foreach ($payments as $payment) {
            if (microtime(true) >= $deadline) {
                break;
            }
            try {
                $result = $service->refresh($payment);
                $failed += $result->reconciliation_issue !== null ? 1 : 0;
                $count++;
            } catch (\Throwable) {
                // Only safe local identity. Raw transport/SQL errors can contain secrets.
                $this->error('Reconciliation could not finish for payment '.$payment->id);
                $failed++;
            }
        }
        $this->info("Checked {$count} payment(s); {$failed} need attention.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
