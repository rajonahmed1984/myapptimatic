<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Models\Invoice;
use App\Support\SystemLogger;
use App\Support\LicenseInvoiceGrace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SuspendPastDueLicenses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'licenses:suspend-past-due
                            {--expiry-only : Only suspend licenses whose own expiry grace has elapsed}
                            {--invoice-only : Only suspend licenses with outstanding due invoices}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically suspend active licenses that are 3+ days past due';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today()->toDateString();
        $threeDaysAgo = Carbon::today()->subDays(3)->toDateString();

        $this->info("Running license auto-suspension check...");

        // Condition 1: still-active licenses whose own expiry grace has run out.
        // billing:run marks lapsed licenses `expired` the day after expiry; this
        // catches anything that slipped past it (e.g. billing:run not running).
        $licensesByExpiry = $this->option('invoice-only') ? collect() : License::query()
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $threeDaysAgo)
            ->where(function ($query) use ($today) {
                $query->whereNull('auto_suspend_override_until')
                      ->orWhere('auto_suspend_override_until', '<', $today);
            })
            ->whereDoesntHave('subscription.customer', function ($query) use ($today) {
                $query->where('access_override_until', '>=', $today);
            })
            ->get();

        $suspendedCount = 0;

        foreach ($licensesByExpiry as $license) {
            $license->update(['status' => 'suspended']);
            SystemLogger::write('activity', "License suspended automatically (expired).", [
                'license_id' => $license->id,
                'license_key' => $license->license_key,
                'expires_at' => $license->expires_at?->toDateString(),
            ]);
            $this->info("Suspended license #{$license->id} due to expiry: {$license->expires_at?->toDateString()}");
            $suspendedCount++;
        }

        // Condition 2: At 11:59 PM on the third day of the month, suspend
        // active licenses that still have an outstanding invoice due by today.
        $licensesByInvoices = ($this->option('expiry-only') || ! LicenseInvoiceGrace::hasEnded()) ? collect() : License::query()
            ->where('status', 'active')
            ->where(function ($query) use ($today) {
                $query->whereNull('auto_suspend_override_until')
                      ->orWhere('auto_suspend_override_until', '<', $today);
            })
            ->whereHas('subscription.invoices', function ($invoiceQuery) use ($today) {
                $invoiceQuery->whereIn('status', ['unpaid', 'overdue'])
                    ->whereDate('due_date', '<=', $today)
                    ->whereRaw("(COALESCE(total, 0) - COALESCE((SELECT SUM(CASE WHEN type IN ('payment', 'credit') THEN amount ELSE 0 END) FROM accounting_entries WHERE accounting_entries.invoice_id = invoices.id), 0)) > 0.009");
            })
            ->whereDoesntHave('subscription.customer', function ($query) use ($today) {
                $query->where('access_override_until', '>=', $today);
            })
            ->get();

        foreach ($licensesByInvoices as $license) {
            $license->update(['status' => 'suspended']);
            SystemLogger::write('activity', "License suspended automatically (past-due invoice).", [
                'license_id' => $license->id,
                'license_key' => $license->license_key,
            ]);
            $this->info("Suspended license #{$license->id} due to past-due invoice.");
            $suspendedCount++;
        }

        $this->info("Completed auto-suspension check. Suspended {$suspendedCount} licenses.");

        return 0;
    }
}
