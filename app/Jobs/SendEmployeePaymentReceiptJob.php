<?php

namespace App\Jobs;

use App\Services\EmployeePaymentNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendEmployeePaymentReceiptJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $sourceType,
        public readonly int $sourceId
    ) {
    }

    public function handle(EmployeePaymentNotificationService $service): void
    {
        if ($this->sourceType === 'employee_payout') {
            $service->sendEmployeePayoutReceipt($this->sourceId);
            return;
        }

        if ($this->sourceType === 'payroll_audit') {
            $service->sendPayrollPaymentReceipt($this->sourceId);
        }
    }
}
