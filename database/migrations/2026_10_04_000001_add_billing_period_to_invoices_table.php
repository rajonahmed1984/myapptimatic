<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record the service period a subscription invoice bills for, so paying an
 * invoice can extend the license to exactly that period. Until now the period
 * was only inferred from the subscription window, which has usually rolled on
 * to the next (unpaid) term by the time the invoice is paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('due_date');
            $table->date('period_end')->nullable()->after('period_start');
        });

        $this->backfillFromItemDescriptions();
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['period_start', 'period_end']);
        });
    }

    /**
     * Every generated subscription invoice describes its period as
     * "<start> to <end>" in the first line item, in Y-m-d (billing run) or
     * d-m-Y (client order) form. Rows that do not match are left null and
     * fall back to the old inference.
     */
    private function backfillFromItemDescriptions(): void
    {
        DB::table('invoices')
            ->whereNotNull('subscription_id')
            ->whereNull('period_end')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $description = (string) DB::table('invoice_items')
                        ->where('invoice_id', $invoice->id)
                        ->orderBy('id')
                        ->value('description');

                    $period = $this->parsePeriod($description);

                    if ($period === null) {
                        continue;
                    }

                    DB::table('invoices')->where('id', $invoice->id)->update([
                        'period_start' => $period[0],
                        'period_end' => $period[1],
                    ]);
                }
            });
    }

    private function parsePeriod(string $description): ?array
    {
        $patterns = [
            '/(\d{4}-\d{2}-\d{2}) to (\d{4}-\d{2}-\d{2})/' => 'Y-m-d',
            '/(\d{2}-\d{2}-\d{4}) to (\d{2}-\d{2}-\d{4})/' => 'd-m-Y',
        ];

        foreach ($patterns as $pattern => $format) {
            if (! preg_match($pattern, $description, $matches)) {
                continue;
            }

            try {
                $start = Carbon::createFromFormat($format, $matches[1])->toDateString();
                $end = Carbon::createFromFormat($format, $matches[2])->toDateString();
            } catch (\Throwable) {
                return null;
            }

            return $start <= $end ? [$start, $end] : null;
        }

        return null;
    }
};
