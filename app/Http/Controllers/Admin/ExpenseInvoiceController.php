<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use App\Models\EmployeePayout;
use App\Models\Expense;
use App\Models\ExpenseInvoice;
use App\Models\ExpenseInvoicePayment;
use App\Models\PaymentMethod;
use App\Models\PayrollItem;
use App\Models\RecurringExpenseAdvance;
use App\Services\ExpenseInvoiceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpenseInvoiceController extends Controller
{
    public function store(Request $request, ExpenseInvoiceService $invoiceService): RedirectResponse
    {
        $data = $request->validate([
            'source_type' => ['required', Rule::in(['expense', 'payroll_item', 'employee_payout', 'commission_payout'])],
            'source_id' => ['required', 'integer'],
        ]);

        $userId = $request->user()->id;

        switch ($data['source_type']) {
            case 'expense':
                $expense = Expense::query()->findOrFail($data['source_id']);
                $invoiceService->createForExpense($expense, $userId);
                break;
            case 'payroll_item':
                $item = PayrollItem::query()
                    ->with(['employee', 'period'])
                    ->whereKey($data['source_id'])
                    ->where('status', 'paid')
                    ->whereNotNull('paid_at')
                    ->firstOrFail();
                $amount = (float) ($item->net_pay ?? $item->gross_pay ?? 0);
                $notes = null;
                if ($item->period) {
                    $notes = sprintf(
                        'Period: %s to %s',
                        $item->period->start_date?->format('Y-m-d'),
                        $item->period->end_date?->format('Y-m-d')
                    );
                }
                $invoiceService->createForSource(
                    'payroll_item',
                    $item->id,
                    'salary',
                    $amount,
                    $item->paid_at,
                    $userId,
                    ['notes' => $notes]
                );
                break;
            case 'employee_payout':
                $payout = EmployeePayout::query()
                    ->with('employee')
                    ->whereKey($data['source_id'])
                    ->whereNotNull('paid_at')
                    ->firstOrFail();
                $projectIds = (array) ($payout->metadata['project_ids'] ?? []);
                $notes = ! empty($projectIds) ? 'Projects: '.implode(', ', $projectIds) : null;
                $invoiceService->createForSource(
                    'employee_payout',
                    $payout->id,
                    'contract_payout',
                    (float) $payout->amount,
                    $payout->paid_at,
                    $userId,
                    ['notes' => $notes]
                );
                break;
            case 'commission_payout':
                $payout = CommissionPayout::query()
                    ->with('salesRep')
                    ->whereKey($data['source_id'])
                    ->where('status', 'paid')
                    ->whereNotNull('paid_at')
                    ->firstOrFail();
                $notes = $payout->reference ? 'Reference: '.$payout->reference : null;
                $invoiceService->createForSource(
                    'commission_payout',
                    $payout->id,
                    'sales_payout',
                    (float) $payout->total_amount,
                    $payout->paid_at,
                    $userId,
                    ['notes' => $notes]
                );
                break;
        }

        return back()->with('status', 'Expense invoice generated.');
    }

    public function markPaid(Request $request, ExpenseInvoice $expenseInvoice): RedirectResponse
    {
        $allowedPaymentMethods = PaymentMethod::allowedCodes();
        if ($this->supportsAdvancePayment($expenseInvoice)) {
            $allowedPaymentMethods[] = 'advance';
        }

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(array_values(array_unique($allowedPaymentMethods)))],
            'payment_type' => ['required', Rule::in(['full', 'partial'])],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['required', 'date_format:Y-m-d'],
        ]);

        $paymentType = (string) $data['payment_type'];
        $paymentMethod = (string) $data['payment_method'];
        $paidAt = Carbon::parse((string) $data['paid_at'])->startOfDay();
        $paymentReference = trim((string) ($data['payment_reference'] ?? ''));
        $note = trim((string) ($data['note'] ?? ''));
        $requestedAmount = round((float) ($data['amount'] ?? 0), 2, PHP_ROUND_HALF_UP);

        if ($paymentType === 'partial' && $requestedAmount <= 0) {
            return back()->withErrors(['amount' => 'Partial payment amount is required.']);
        }

        $result = DB::transaction(function () use (
            $expenseInvoice,
            $request,
            &$paymentType,
            $paymentMethod,
            $paidAt,
            $paymentReference,
            $note,
            $requestedAmount
        ) {
            /** @var ExpenseInvoice $invoice */
            $invoice = ExpenseInvoice::query()
                ->lockForUpdate()
                ->findOrFail($expenseInvoice->id);

            $invoiceAmount = round((float) ($invoice->amount ?? 0), 2, PHP_ROUND_HALF_UP);
            $paidBefore = round((float) $invoice->payments()->sum('amount'), 2, PHP_ROUND_HALF_UP);
            if ($invoice->status === 'paid' && $paidBefore <= 0) {
                $paidBefore = $invoiceAmount;
            }
            $remainingBefore = round(max(0, $invoiceAmount - $paidBefore), 2, PHP_ROUND_HALF_UP);

            if ($remainingBefore <= 0) {
                return [
                    'ok' => false,
                    'error' => 'This expense invoice is already fully paid.',
                ];
            }

            $paymentAmount = $paymentType === 'full'
                ? $remainingBefore
                : $requestedAmount;

            if ($paymentAmount <= 0) {
                return [
                    'ok' => false,
                    'error' => 'Payment amount must be greater than zero.',
                ];
            }

            if ($paymentAmount > $remainingBefore) {
                return [
                    'ok' => false,
                    'error' => 'Payment amount exceeds remaining due ('.number_format($remainingBefore, 2).').',
                ];
            }

            $advanceBalanceAfter = null;
            if ($paymentMethod === 'advance') {
                $recurringExpenseId = (int) ($invoice->expense()
                    ->whereNotNull('recurring_expense_id')
                    ->value('recurring_expense_id') ?? 0);

                if ($recurringExpenseId <= 0) {
                    return [
                        'ok' => false,
                        'error' => 'Advance payment is available only for recurring expense invoices.',
                    ];
                }

                $advanceBalance = $this->recurringAdvanceBalance($recurringExpenseId);
                if ($advanceBalance <= 0) {
                    return [
                        'ok' => false,
                        'error' => 'No advance balance available for this recurring expense.',
                    ];
                }

                $paymentAmount = round(min($paymentAmount, $advanceBalance), 2, PHP_ROUND_HALF_UP);
                if ($paymentAmount <= 0) {
                    return [
                        'ok' => false,
                        'error' => 'No advance balance available for this recurring expense.',
                    ];
                }

                $paymentType = $paymentAmount >= ($remainingBefore - 0.009) ? 'full' : 'partial';
                $advanceBalanceAfter = round(max(0, $advanceBalance - $paymentAmount), 2, PHP_ROUND_HALF_UP);
            }

            $paymentNote = $note !== '' ? $note : null;
            if ($paymentMethod === 'advance' && $paymentNote === null) {
                $paymentNote = 'Auto-applied from recurring advance balance.';
            }

            ExpenseInvoicePayment::query()->create([
                'expense_invoice_id' => $invoice->id,
                'payment_method' => $paymentMethod,
                'payment_type' => $paymentType,
                'amount' => $paymentAmount,
                'paid_at' => $paidAt->toDateString(),
                'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                'note' => $paymentNote,
                'created_by' => $request->user()?->id,
            ]);

            $paidAfter = round($paidBefore + $paymentAmount, 2, PHP_ROUND_HALF_UP);
            $remainingAfter = round(max(0, $invoiceAmount - $paidAfter), 2, PHP_ROUND_HALF_UP);
            $isFullyPaid = $remainingAfter <= 0.009;

            $status = $invoice->status;
            if ($isFullyPaid) {
                $status = 'paid';
            } elseif ($invoice->due_date && $invoice->due_date->isPast()) {
                $status = 'overdue';
            } else {
                $status = 'unpaid';
            }

            $invoice->update([
                'status' => $status,
                'paid_at' => $isFullyPaid ? $paidAt : null,
            ]);

            return [
                'ok' => true,
                'is_fully_paid' => $isFullyPaid,
                'remaining_after' => $remainingAfter,
                'payment_amount' => $paymentAmount,
                'payment_method' => $paymentMethod,
                'advance_balance_after' => $advanceBalanceAfter,
            ];
        });

        if (! ($result['ok'] ?? false)) {
            return back()->withErrors(['expense_invoice' => $result['error'] ?? 'Payment failed.']);
        }

        if ($result['is_fully_paid'] ?? false) {
            return back()->with('status', 'Expense invoice fully paid.');
        }

        if (($result['payment_method'] ?? '') === 'advance') {
            return back()->with(
                'status',
                'Advance applied ('.number_format((float) ($result['payment_amount'] ?? 0), 2).'). Remaining due: '.number_format((float) ($result['remaining_after'] ?? 0), 2).'. Advance balance left: '.number_format((float) ($result['advance_balance_after'] ?? 0), 2)
            );
        }

        return back()->with(
            'status',
            'Partial payment saved ('.number_format((float) ($result['payment_amount'] ?? 0), 2).'). Remaining: '.number_format((float) ($result['remaining_after'] ?? 0), 2)
        );
    }

    private function supportsAdvancePayment(ExpenseInvoice $expenseInvoice): bool
    {
        if (! $expenseInvoice->expense_id) {
            return false;
        }

        return Expense::query()
            ->whereKey($expenseInvoice->expense_id)
            ->whereNotNull('recurring_expense_id')
            ->exists();
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
}
