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
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(PaymentMethod::allowedCodes())],
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
            $paymentType,
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

            ExpenseInvoicePayment::query()->create([
                'expense_invoice_id' => $invoice->id,
                'payment_method' => $paymentMethod,
                'payment_type' => $paymentType,
                'amount' => $paymentAmount,
                'paid_at' => $paidAt->toDateString(),
                'payment_reference' => $paymentReference !== '' ? $paymentReference : null,
                'note' => $note !== '' ? $note : null,
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
            ];
        });

        if (! ($result['ok'] ?? false)) {
            return back()->withErrors(['expense_invoice' => $result['error'] ?? 'Payment failed.']);
        }

        if ($result['is_fully_paid'] ?? false) {
            return back()->with('status', 'Expense invoice fully paid.');
        }

        return back()->with(
            'status',
            'Partial payment saved ('.number_format((float) ($result['payment_amount'] ?? 0), 2).'). Remaining: '.number_format((float) ($result['remaining_after'] ?? 0), 2)
        );
    }
}
