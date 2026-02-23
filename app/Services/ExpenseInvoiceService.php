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
    public function createForExpense(
        Expense $expense,
        int $createdBy,
        string $status = 'issued',
        ?Carbon $dueDate = null
    ): ExpenseInvoice
    {
        $expenseType = $expense->type === 'recurring' ? 'recurring' : 'manual';
        $invoiceDate = $expense->expense_date ? Carbon::parse($expense->expense_date) : now();
        $overrides = [
            'expense_id' => $expense->id,
            'notes' => $expense->notes,
            'status' => $status,
        ];
        if ($dueDate) {
            $overrides['due_date'] = $dueDate->toDateString();
        }

        return $this->createForSource(
            'expense',
            $expense->id,
            $expenseType,
            $expense->amount,
            $invoiceDate,
            $createdBy,
            $overrides
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
                $updates = [];

                if (! empty($overrides['due_date']) && ! $existing->due_date) {
                    $updates['due_date'] = $overrides['due_date'];
                }

                if (! empty($updates)) {
                    $existing->update($updates);
                    $existing->refresh();
                }

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

    public function syncOverdueStatuses(?int $recurringExpenseId = null): void
    {
        $today = Carbon::today()->toDateString();

        $base = ExpenseInvoice::query()
            ->whereNotNull('due_date')
            ->whereIn('status', ['issued', 'unpaid', 'overdue']);

        if ($recurringExpenseId) {
            $base->whereHas('expense', function ($query) use ($recurringExpenseId) {
                $query->where('recurring_expense_id', $recurringExpenseId);
            });
        }

        (clone $base)
            ->whereIn('status', ['issued', 'unpaid'])
            ->whereDate('due_date', '<', $today)
            ->update(['status' => 'overdue']);

        (clone $base)
            ->where('status', 'overdue')
            ->whereDate('due_date', '>=', $today)
            ->update(['status' => 'unpaid']);

        (clone $base)
            ->where('status', 'issued')
            ->whereDate('due_date', '>=', $today)
            ->update(['status' => 'unpaid']);
    }
}
