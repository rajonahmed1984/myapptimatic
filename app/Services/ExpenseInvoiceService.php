<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseInvoice;
use App\Models\Setting;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExpenseInvoiceService
{
    public function createForExpense(Expense $expense, int $createdBy, string $status = 'issued'): ExpenseInvoice
    {
        $expenseType = $expense->type === 'recurring' ? 'recurring' : 'manual';

        return $this->createForSource(
            'expense',
            $expense->id,
            $expenseType,
            $expense->amount,
            $expense->expense_date ? Carbon::parse($expense->expense_date) : now(),
            $createdBy,
            [
                'expense_id' => $expense->id,
                'notes' => $expense->notes,
                'status' => $status,
            ]
        );
    }

    public function createForSource(
        string $sourceType,
        int $sourceId,
        string $expenseType,
        float $amount,
        Carbon $invoiceDate,
        int $createdBy,
        array $overrides = []
    ): ExpenseInvoice {
        return DB::transaction(function () use (
            $sourceType,
            $sourceId,
            $expenseType,
            $amount,
            $invoiceDate,
            $createdBy,
            $overrides
        ) {
            $existing = ExpenseInvoice::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
            if (! Currency::isAllowed($currency)) {
                $currency = Currency::DEFAULT;
            }

            $invoiceNo = $this->nextInvoiceNumber($invoiceDate);

            return ExpenseInvoice::create([
                'expense_id' => $overrides['expense_id'] ?? null,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'expense_type' => $expenseType,
                'invoice_no' => $invoiceNo,
                'status' => $overrides['status'] ?? 'issued',
                'invoice_date' => $invoiceDate->toDateString(),
                'due_date' => $overrides['due_date'] ?? null,
                'amount' => $amount,
                'currency' => $currency,
                'notes' => $overrides['notes'] ?? null,
                'created_by' => $createdBy,
            ]);
        });
    }

    private function nextInvoiceNumber(Carbon $date): string
    {
        $year = $date->format('Y');
        $prefix = "EXP-{$year}-";

        $last = ExpenseInvoice::query()
            ->where('invoice_no', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $lastNumber = 0;
        if ($last) {
            $parts = explode('-', (string) $last->invoice_no);
            $lastNumber = (int) ($parts[2] ?? 0);
        }

        $next = $lastNumber + 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
