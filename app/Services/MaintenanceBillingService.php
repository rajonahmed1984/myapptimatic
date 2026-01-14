<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ProjectMaintenance;
use App\Models\Setting;
use App\Services\AdminNotificationService;
use App\Services\ClientNotificationService;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceBillingService
{
    public function __construct(
        private BillingService $billingService,
        private AdminNotificationService $adminNotifications,
        private ClientNotificationService $clientNotifications
    )
    {
    }

    public function generateInvoicesForDueMaintenances(?Carbon $today = null): array
    {
        $today = $today ?? Carbon::today();
        $generated = 0;
        $skipped = 0;

        $maintenanceIds = ProjectMaintenance::query()
            ->where('status', 'active')
            ->where('auto_invoice', true)
            ->whereDate('next_billing_date', '<=', $today)
            ->pluck('id');

        foreach ($maintenanceIds as $maintenanceId) {
            $invoice = $this->billMaintenance((int) $maintenanceId, $today);
            if ($invoice) {
                $generated++;
                $this->adminNotifications->sendInvoiceCreated($invoice);
                $this->clientNotifications->sendInvoiceCreated($invoice);
            } else {
                $skipped++;
            }
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
        ];
    }

    private function billMaintenance(int $maintenanceId, Carbon $today): ?Invoice
    {
        return DB::transaction(function () use ($maintenanceId, $today) {
            $maintenance = ProjectMaintenance::query()
                ->lockForUpdate()
                ->with('project:id,currency')
                ->find($maintenanceId);

            if (! $maintenance) {
                return null;
            }

            if ($maintenance->status !== 'active' || ! $maintenance->auto_invoice) {
                return null;
            }

            $billingDate = $maintenance->next_billing_date ?? $maintenance->start_date;
            if (! $billingDate) {
                return null;
            }

            $billingDate = Carbon::parse($billingDate)->startOfDay();
            if ($billingDate->greaterThan($today)) {
                return null;
            }

            $nextBillingDate = $this->nextBillingDate($billingDate, $maintenance->billing_cycle);

            $existingInvoice = Invoice::query()
                ->where('maintenance_id', $maintenance->id)
                ->whereDate('issue_date', '>=', $billingDate->toDateString())
                ->whereDate('issue_date', '<', $nextBillingDate->toDateString())
                ->exists();

            if ($existingInvoice) {
                $maintenance->update([
                    'last_billed_at' => $maintenance->last_billed_at ?? Carbon::now(),
                    'next_billing_date' => $nextBillingDate->toDateString(),
                ]);

                return null;
            }

            $amount = (float) $maintenance->amount;
            if ($amount <= 0) {
                return null;
            }

            $currency = $maintenance->currency
                ?: ($maintenance->project?->currency ?: strtoupper((string) Setting::getValue('currency')));
            $dueDays = (int) Setting::getValue('invoice_due_days');
            $dueDate = $billingDate->copy()->addDays($dueDays);

            $invoice = Invoice::create([
                'customer_id' => $maintenance->customer_id,
                'project_id' => $maintenance->project_id,
                'maintenance_id' => $maintenance->id,
                'number' => $this->billingService->nextInvoiceNumber(),
                'status' => 'unpaid',
                'issue_date' => $billingDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'subtotal' => $amount,
                'late_fee' => 0,
                'total' => $amount,
                'currency' => strtoupper($currency),
                'type' => 'project_maintenance',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => sprintf('Maintenance - %s (%s)', $maintenance->title, $maintenance->billing_cycle),
                'quantity' => 1,
                'unit_price' => $amount,
                'line_total' => $amount,
            ]);

            $maintenance->update([
                'last_billed_at' => Carbon::now(),
                'next_billing_date' => $nextBillingDate->toDateString(),
            ]);

            SystemLogger::write('activity', 'Maintenance invoice created.', [
                'maintenance_id' => $maintenance->id,
                'project_id' => $maintenance->project_id,
                'customer_id' => $maintenance->customer_id,
                'invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }

    private function nextBillingDate(Carbon $current, string $cycle): Carbon
    {
        if ($cycle === 'yearly') {
            return $current->copy()->addYear();
        }

        return $current->copy()->addMonthNoOverflow();
    }
}
