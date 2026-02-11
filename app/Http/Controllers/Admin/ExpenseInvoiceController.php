<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use App\Models\EmployeePayout;
use App\Models\Expense;
use App\Models\ExpenseInvoice;
use App\Models\PayrollItem;
use App\Services\ExpenseInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        if ($expenseInvoice->status !== 'paid') {
            $expenseInvoice->update(['status' => 'paid']);
        }

        return back()->with('status', 'Expense invoice marked as paid.');
    }
}
