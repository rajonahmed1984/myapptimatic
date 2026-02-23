<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseInvoice;
use App\Models\ExpenseInvoicePayment;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseAdvance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RecurringExpenseGenerator
{
    public const DEFAULT_LOOKAHEAD_DAYS = 3;

    public function __construct(private ExpenseInvoiceService $invoiceService)
    {
    }

    public function generate(?Carbon $asOfDate = null, ?int $templateId = null, int $lookaheadDays = 0): array
    {
        $asOf = ($asOfDate ?? now())->startOfDay();
        $lookaheadDays = max(0, $lookaheadDays);
        $runUntil = $asOf->copy()->addDays($lookaheadDays);
        $created = 0;
        $processed = 0;

        $query = RecurringExpense::query()
            ->where('status', 'active')
            ->whereNotNull('next_run_date')
            ->whereDate('next_run_date', '<=', $runUntil->toDateString())
            ->orderBy('next_run_date');

        if ($templateId) {
            $query->whereKey($templateId);
        }

        $query->chunkById(50, function ($templates) use (&$created, &$processed, $runUntil) {
            foreach ($templates as $template) {
                $processed++;
                $created += $this->generateForTemplate($template, $runUntil);
            }
        });

        return [
            'processed' => $processed,
            'created' => $created,
        ];
    }

    private function generateForTemplate(RecurringExpense $template, Carbon $asOf): int
    {
        $created = 0;

        DB::transaction(function () use ($template, $asOf, &$created) {
            $template->refresh();

            if ($template->status !== 'active' || ! $template->next_run_date) {
                return;
            }

            $advanceBalance = $this->recurringAdvanceBalance($template->id);
            $nextRun = Carbon::parse($template->next_run_date)->startOfDay();
            $endDate = $template->end_date ? Carbon::parse($template->end_date)->startOfDay() : null;

            while ($nextRun->lessThanOrEqualTo($asOf)) {
                if ($endDate && $nextRun->greaterThan($endDate)) {
                    $template->update([
                        'status' => 'stopped',
                        'next_run_date' => null,
                    ]);
                    return;
                }

                $expense = Expense::query()
                    ->where('recurring_expense_id', $template->id)
                    ->whereDate('expense_date', $nextRun->toDateString())
                    ->first();

                if (! $expense) {
                    $expense = Expense::query()->create([
                        'category_id' => $template->category_id,
                        'recurring_expense_id' => $template->id,
                        'title' => $template->title,
                        'amount' => $template->amount,
                        'expense_date' => $nextRun->toDateString(),
                        'notes' => $template->notes,
                        'type' => 'recurring',
                        'created_by' => $template->created_by,
                    ]);
                    $created++;
                }

                $createdBy = (int) ($template->created_by ?? $expense->created_by ?? 1);
                $invoice = $this->invoiceService->createForExpense($expense, $createdBy, 'unpaid', $nextRun);

                if ($advanceBalance > 0) {
                    $applied = $this->applyAdvanceToInvoice($invoice, $nextRun, $template->created_by);
                    $advanceBalance = round(max(0, $advanceBalance - $applied), 2, PHP_ROUND_HALF_UP);
                }

                $nextRun = $this->advanceDate($nextRun, $template->recurrence_type, $template->recurrence_interval);
            }

            $template->update([
                'next_run_date' => $nextRun->toDateString(),
            ]);
        });

        return $created;
    }

    private function recurringAdvanceBalance(int $recurringExpenseId): float
    {
        $totalAdvance = round(
            (float) RecurringExpenseAdvance::query()
                ->where('recurring_expense_id', $recurringExpenseId)
                ->sum('amount'),
            2,
            PHP_ROUND_HALF_UP
        );

        $usedAdvance = round(
            (float) ExpenseInvoicePayment::query()
                ->where('payment_method', 'advance')
                ->whereHas('invoice.expense', function ($query) use ($recurringExpenseId) {
                    $query->where('recurring_expense_id', $recurringExpenseId);
                })
                ->sum('amount'),
            2,
            PHP_ROUND_HALF_UP
        );

        return round(max(0, $totalAdvance - $usedAdvance), 2, PHP_ROUND_HALF_UP);
    }

    private function applyAdvanceToInvoice(ExpenseInvoice $invoice, Carbon $paidAt, ?int $createdBy): float
    {
        $invoiceAmount = round((float) ($invoice->amount ?? 0), 2, PHP_ROUND_HALF_UP);
        $paidBefore = round((float) $invoice->payments()->sum('amount'), 2, PHP_ROUND_HALF_UP);
        if ($invoice->status === 'paid' && $paidBefore <= 0) {
            $paidBefore = $invoiceAmount;
        }

        $remainingBefore = round(max(0, $invoiceAmount - $paidBefore), 2, PHP_ROUND_HALF_UP);
        if ($remainingBefore <= 0) {
            return 0;
        }

        $recurringExpenseId = (int) ($invoice->expense()
            ->whereNotNull('recurring_expense_id')
            ->value('recurring_expense_id') ?? 0);
        if ($recurringExpenseId <= 0) {
            return 0;
        }

        $advanceBalance = $this->recurringAdvanceBalance($recurringExpenseId);
        if ($advanceBalance <= 0) {
            return 0;
        }

        $paymentAmount = round(min($remainingBefore, $advanceBalance), 2, PHP_ROUND_HALF_UP);
        if ($paymentAmount <= 0) {
            return 0;
        }

        $isFullPayment = $paymentAmount >= ($remainingBefore - 0.009);

        ExpenseInvoicePayment::query()->create([
            'expense_invoice_id' => $invoice->id,
            'payment_method' => 'advance',
            'payment_type' => $isFullPayment ? 'full' : 'partial',
            'amount' => $paymentAmount,
            'paid_at' => $paidAt->toDateString(),
            'payment_reference' => null,
            'note' => 'Auto-applied from recurring advance balance.',
            'created_by' => $createdBy,
        ]);

        $paidAfter = round($paidBefore + $paymentAmount, 2, PHP_ROUND_HALF_UP);
        $remainingAfter = round(max(0, $invoiceAmount - $paidAfter), 2, PHP_ROUND_HALF_UP);
        $isPaid = $remainingAfter <= 0.009;

        $status = $invoice->status;
        if ($isPaid) {
            $status = 'paid';
        } elseif ($invoice->due_date && $invoice->due_date->startOfDay()->lessThan(now()->startOfDay())) {
            $status = 'overdue';
        } else {
            $status = 'unpaid';
        }

        $invoice->update([
            'status' => $status,
            'paid_at' => $isPaid ? $paidAt : null,
        ]);

        return $paymentAmount;
    }

    private function advanceDate(Carbon $date, string $type, int $interval): Carbon
    {
        $interval = max(1, $interval);

        if ($type === 'yearly') {
            return $date->copy()->addYearsNoOverflow($interval);
        }

        return $date->copy()->addMonthsNoOverflow($interval);
    }
}
