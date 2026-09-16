<?php

namespace App\Console\Commands;

use App\Domain\Payments\MobileMoneyPayment;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use Illuminate\Console\Command;

final class RecoverSnippeInitiation extends Command
{
    protected $signature = 'payments:recover-snippe-initiation {payment : Existing local payment ID} {--actor= : Authorized operator user ID} {--reason= : Reason for replay; never include credentials} {--execute : Explicitly allow the stored payment request to be sent}';

    protected $description = 'Explicitly recover one Mobile Money initiation using its original snapshot/key; may send a payment prompt';

    public function handle(MobileMoneyPayment $payments): int
    {
        if (! $this->option('execute') || ! $this->option('actor') || ! $this->option('reason')) {
            $this->error('No request sent. Recovery requires --execute, an authorized --actor and --reason. It may send a payment prompt.');

            return self::FAILURE;
        }
        try {
            $actor = User::query()->findOrFail($this->option('actor'));
            $payment = Payment::query()->findOrFail($this->argument('payment'));
            $result = $payments->recoverInitiation($payment, $actor, (string) $this->option('reason'));
            $this->info('Existing attempt status: '.$result->status->value.'. Review the Order payment history before any further action.');

            return $result->reconciliation_issue === null ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable) {
            // Exceptions may contain encrypted snapshots, HTTP bodies or credentials.
            $this->error('Recovery could not proceed or finish. Check operator permissions, attempt eligibility, retry timing and the Order payment history.');

            return self::FAILURE;
        }
    }
}
