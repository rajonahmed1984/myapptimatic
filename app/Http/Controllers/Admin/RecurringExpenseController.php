<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\ExpenseInvoice;
use App\Models\ExpenseInvoicePayment;
use App\Models\PaymentMethod;
use App\Models\RecurringExpense;
use App\Models\RecurringExpenseAdvance;
use App\Models\Setting;
use App\Services\ExpenseInvoiceService;
use App\Support\Currency;
use App\Support\HybridUiResponder;
use App\Support\UiFeature;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class RecurringExpenseController extends Controller
{
    public function index(
        Request $request,
        ExpenseInvoiceService $invoiceService,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $invoiceService->syncOverdueStatuses();
        $today = Carbon::today()->toDateString();

        $recurringExpenses = RecurringExpense::query()
            ->select('recurring_expenses.*')
            ->addSelect([
                'next_due_date' => ExpenseInvoice::query()
                    ->select('expense_invoices.due_date')
                    ->join('expenses', 'expenses.id', '=', 'expense_invoices.expense_id')
                    ->whereColumn('expenses.recurring_expense_id', 'recurring_expenses.id')
                    ->where('expense_invoices.source_type', 'expense')
                    ->where('expense_invoices.status', '!=', 'paid')
                    ->whereNotNull('expense_invoices.due_date')
                    ->whereDate('expense_invoices.due_date', '>=', $today)
                    ->orderBy('expense_invoices.due_date')
                    ->limit(1),
            ])
            ->with('category')
            ->withSum('advances', 'amount')
            ->orderByDesc('next_run_date')
            ->orderByDesc('id')
            ->paginate(20);

        $paymentMethods = PaymentMethod::dropdownOptions();

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_EXPENSES_RECURRING_INDEX,
            'admin.expenses.recurring.index',
            compact('recurringExpenses', 'paymentMethods', 'currencyCode', 'currencySymbol'),
            'Admin/Expenses/Recurring/Index',
            $this->indexInertiaProps($recurringExpenses, $paymentMethods, $currencyCode, $currencySymbol)
        );
    }

    public function create(): View
    {
        $categories = ExpenseCategory::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.expenses.recurring.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $category = ExpenseCategory::query()->find($data['category_id']);
        if ($category && $category->status !== 'active') {
            return back()->withErrors(['category_id' => 'Category is inactive.'])->withInput();
        }
        $data['created_by'] = $request->user()->id;
        $data['next_run_date'] = $data['start_date'];
        $data['status'] = 'active';

        RecurringExpense::create($data);

        return redirect()->route('admin.expenses.recurring.index')
            ->with('status', 'Recurring expense created.');
    }

    public function edit(RecurringExpense $recurringExpense): View
    {
        $categories = ExpenseCategory::query()
            ->orderBy('name')
            ->get();

        return view('admin.expenses.recurring.edit', compact('recurringExpense', 'categories'));
    }

    public function show(
        Request $request,
        RecurringExpense $recurringExpense,
        ExpenseInvoiceService $invoiceService,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $recurringExpense->load('category');
        $this->backfillMissingDueDates($recurringExpense->id);
        $invoiceService->syncOverdueStatuses($recurringExpense->id);

        $baseInvoices = ExpenseInvoice::query()
            ->where('source_type', 'expense')
            ->whereHas('expense', function ($query) use ($recurringExpense) {
                $query->where('recurring_expense_id', $recurringExpense->id);
            });

        $invoices = (clone $baseInvoices)
            ->with('expense')
            ->withSum('payments', 'amount')
            ->withMax('payments', 'paid_at')
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(20);

        $today = Carbon::today()->toDateString();
        $totalInvoices = (clone $baseInvoices)->count();
        $paidCount = (clone $baseInvoices)->where('status', 'paid')->count();
        $unpaidCount = (clone $baseInvoices)->where('status', '!=', 'paid')->count();
        $overdueCount = (clone $baseInvoices)
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();
        $nextDueDate = (clone $baseInvoices)
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->orderBy('due_date')
            ->value('due_date');

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);
        $paymentMethods = PaymentMethod::dropdownOptions();
        $advancePayments = $recurringExpense->advances()
            ->with('creator')
            ->latest('paid_at')
            ->latest('id')
            ->paginate(10, ['*'], 'advances_page');
        $advanceTotal = (float) $recurringExpense->advances()->sum('amount');
        $advanceUsed = (float) ExpenseInvoicePayment::query()
            ->where('payment_method', 'advance')
            ->whereHas('invoice.expense', function ($query) use ($recurringExpense) {
                $query->where('recurring_expense_id', $recurringExpense->id);
            })
            ->sum('amount');
        $advanceBalance = max(0, $advanceTotal - $advanceUsed);

        $bladeProps = [
            'recurringExpense' => $recurringExpense,
            'invoices' => $invoices,
            'totalInvoices' => $totalInvoices,
            'paidCount' => $paidCount,
            'unpaidCount' => $unpaidCount,
            'overdueCount' => $overdueCount,
            'nextDueDate' => $nextDueDate,
            'currencySymbol' => $currencySymbol,
            'currencyCode' => $currencyCode,
            'paymentMethods' => $paymentMethods,
            'advancePayments' => $advancePayments,
            'advanceTotal' => $advanceTotal,
            'advanceUsed' => $advanceUsed,
            'advanceBalance' => $advanceBalance,
        ];

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_EXPENSES_RECURRING_SHOW,
            'admin.expenses.recurring.show',
            $bladeProps,
            'Admin/Expenses/Recurring/Show',
            $this->showInertiaProps(
                $recurringExpense,
                $invoices,
                $advancePayments,
                $totalInvoices,
                $paidCount,
                $unpaidCount,
                $overdueCount,
                $nextDueDate,
                $currencyCode,
                $currencySymbol,
                $paymentMethods,
                $advanceTotal,
                $advanceUsed,
                $advanceBalance
            )
        );
    }

    private function backfillMissingDueDates(int $recurringExpenseId): void
    {
        ExpenseInvoice::query()
            ->where('source_type', 'expense')
            ->whereNull('due_date')
            ->whereHas('expense', function ($query) use ($recurringExpenseId) {
                $query->where('recurring_expense_id', $recurringExpenseId);
            })
            ->with('expense:id,expense_date,recurring_expense_id')
            ->orderBy('id')
            ->chunkById(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $dueDate = $invoice->expense?->expense_date?->toDateString()
                        ?? $invoice->invoice_date?->toDateString();

                    if ($dueDate) {
                        $invoice->update(['due_date' => $dueDate]);
                    }
                }
            });
    }

    public function update(Request $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $data = $this->validatePayload($request, $recurringExpense);

        $category = ExpenseCategory::query()->find($data['category_id']);
        if ($category && $category->status !== 'active' && $category->id !== $recurringExpense->category_id) {
            return back()->withErrors(['category_id' => 'Category is inactive.'])->withInput();
        }

        $recurringExpense->update($data);

        if ($recurringExpense->start_date) {
            if (! $recurringExpense->next_run_date || $recurringExpense->next_run_date->lessThan($recurringExpense->start_date)) {
                $recurringExpense->update(['next_run_date' => $recurringExpense->start_date]);
            }
        }

        return redirect()->route('admin.expenses.recurring.index')
            ->with('status', 'Recurring expense updated.');
    }

    public function storeAdvance(Request $request, RecurringExpense $recurringExpense): RedirectResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(PaymentMethod::allowedCodes())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        RecurringExpenseAdvance::create([
            'recurring_expense_id' => $recurringExpense->id,
            'payment_method' => $data['payment_method'],
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('status', 'Advance payment added.');
    }

    public function resume(RecurringExpense $recurringExpense): RedirectResponse
    {
        if ($recurringExpense->status === 'stopped') {
            return back()->withErrors(['recurring' => 'Stopped recurring expenses cannot be resumed.']);
        }

        $recurringExpense->update(['status' => 'active']);

        return back()->with('status', 'Recurring expense resumed.');
    }

    public function stop(RecurringExpense $recurringExpense): RedirectResponse
    {
        $recurringExpense->update(['status' => 'stopped']);

        return back()->with('status', 'Recurring expense stopped.');
    }

    private function validatePayload(Request $request, ?RecurringExpense $recurringExpense = null): array
    {
        $data = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'recurrence_type' => ['required', Rule::in(['monthly', 'yearly'])],
            'recurrence_interval' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        return $data;
    }

    private function indexInertiaProps(
        LengthAwarePaginator $recurringExpenses,
        $paymentMethods,
        string $currencyCode,
        string $currencySymbol
    ): array {
        $dateFormat = (string) config('app.date_format', 'd-m-Y');

        return [
            'pageTitle' => 'Recurring Expenses',
            'currency' => [
                'code' => $currencyCode,
                'symbol' => $currencySymbol,
            ],
            'routes' => [
                'create' => route('admin.expenses.recurring.create'),
                'back' => route('admin.expenses.index'),
            ],
            'paymentMethods' => collect($paymentMethods)->map(function ($method) {
                return [
                    'code' => (string) ($method->code ?? ''),
                    'name' => (string) ($method->name ?? ''),
                ];
            })->values()->all(),
            'recurringExpenses' => [
                'data' => $recurringExpenses->getCollection()->map(function (RecurringExpense $recurringExpense) use ($dateFormat) {
                    return [
                        'id' => $recurringExpense->id,
                        'title' => $recurringExpense->title,
                        'category_name' => $recurringExpense->category?->name,
                        'amount' => (float) $recurringExpense->amount,
                        'advance_amount' => (float) ($recurringExpense->advances_sum_amount ?? 0),
                        'recurrence_type' => $recurringExpense->recurrence_type,
                        'recurrence_interval' => (int) $recurringExpense->recurrence_interval,
                        'next_due_display' => $this->resolveNextDueDisplay($recurringExpense, $dateFormat),
                        'status' => $recurringExpense->status,
                        'can_resume' => $recurringExpense->status === 'paused',
                        'routes' => [
                            'show' => route('admin.expenses.recurring.show', $recurringExpense),
                            'edit' => route('admin.expenses.recurring.edit', $recurringExpense),
                            'advance_store' => route('admin.expenses.recurring.advance.store', $recurringExpense),
                            'resume' => route('admin.expenses.recurring.resume', $recurringExpense),
                            'stop' => route('admin.expenses.recurring.stop', $recurringExpense),
                        ],
                    ];
                })->values()->all(),
                'links' => $this->paginatorLinks($recurringExpenses),
            ],
        ];
    }

    private function showInertiaProps(
        RecurringExpense $recurringExpense,
        LengthAwarePaginator $invoices,
        LengthAwarePaginator $advancePayments,
        int $totalInvoices,
        int $paidCount,
        int $unpaidCount,
        int $overdueCount,
        ?string $nextDueDate,
        string $currencyCode,
        string $currencySymbol,
        $paymentMethods,
        float $advanceTotal,
        float $advanceUsed,
        float $advanceBalance
    ): array {
        $dateFormat = (string) config('app.date_format', 'd-m-Y');

        return [
            'pageTitle' => 'Recurring Expense',
            'currency' => [
                'code' => $currencyCode,
                'symbol' => $currencySymbol,
            ],
            'routes' => [
                'edit' => route('admin.expenses.recurring.edit', $recurringExpense),
                'back' => route('admin.expenses.recurring.index'),
            ],
            'recurringExpense' => [
                'id' => $recurringExpense->id,
                'title' => $recurringExpense->title,
                'category_name' => $recurringExpense->category?->name ?? 'No category',
                'amount' => (float) $recurringExpense->amount,
                'recurrence_type' => $recurringExpense->recurrence_type,
                'recurrence_interval' => (int) $recurringExpense->recurrence_interval,
                'next_run_display' => $recurringExpense->next_run_date?->format($dateFormat) ?? '--',
            ],
            'stats' => [
                'total_invoices' => $totalInvoices,
                'paid_count' => $paidCount,
                'unpaid_count' => $unpaidCount,
                'overdue_count' => $overdueCount,
                'next_due_display' => $nextDueDate ? Carbon::parse($nextDueDate)->format($dateFormat) : '--',
                'advance_total' => (float) $advanceTotal,
                'advance_used' => (float) $advanceUsed,
                'advance_balance' => (float) $advanceBalance,
            ],
            'paymentMethods' => collect($paymentMethods)->map(function ($method) {
                return [
                    'code' => (string) ($method->code ?? ''),
                    'name' => (string) ($method->name ?? ''),
                ];
            })->values()->all(),
            'advances' => [
                'data' => $advancePayments->getCollection()->map(function (RecurringExpenseAdvance $advance) use ($dateFormat) {
                    return [
                        'id' => $advance->id,
                        'paid_at_display' => $advance->paid_at?->format($dateFormat) ?? '--',
                        'payment_method' => strtoupper((string) $advance->payment_method),
                        'amount' => (float) $advance->amount,
                        'payment_reference' => $advance->payment_reference ?: '--',
                        'note' => $advance->note ?: '--',
                        'creator_name' => $advance->creator?->name ?? '--',
                    ];
                })->values()->all(),
                'links' => $this->paginatorLinks($advancePayments),
            ],
            'invoices' => [
                'data' => $invoices->getCollection()->map(function (ExpenseInvoice $invoice) use ($dateFormat) {
                    $invoiceAmount = round((float) ($invoice->amount ?? 0), 2, PHP_ROUND_HALF_UP);
                    $paidAmount = round((float) ($invoice->payments_sum_amount ?? 0), 2, PHP_ROUND_HALF_UP);
                    if (($invoice->status ?? '') === 'paid' && $paidAmount <= 0) {
                        $paidAmount = $invoiceAmount;
                    }
                    $remainingAmount = round(max(0, $invoiceAmount - $paidAmount), 2, PHP_ROUND_HALF_UP);
                    $isPaid = $remainingAmount <= 0.009;
                    $isPartiallyPaid = $paidAmount > 0 && ! $isPaid;

                    $displayStatus = $isPaid ? 'paid' : ($invoice->status ?? 'unpaid');
                    if (! $isPaid && $invoice->due_date && $invoice->due_date->isPast()) {
                        $displayStatus = 'overdue';
                    } elseif (! $isPaid && $displayStatus === 'issued') {
                        $displayStatus = 'unpaid';
                    }

                    $statusKey = $isPartiallyPaid && $displayStatus !== 'overdue' ? 'partial' : $displayStatus;
                    $statusLabel = $isPartiallyPaid
                        ? ($displayStatus === 'overdue' ? 'Partial overdue' : 'Partially paid')
                        : ucfirst(str_replace('_', ' ', (string) $displayStatus));

                    $paidDate = $invoice->paid_at;
                    if (! $paidDate && ! empty($invoice->payments_max_paid_at)) {
                        try {
                            $paidDate = Carbon::parse((string) $invoice->payments_max_paid_at);
                        } catch (\Throwable $e) {
                            $paidDate = null;
                        }
                    }

                    return [
                        'id' => $invoice->id,
                        'invoice_no' => $invoice->invoice_no,
                        'due_date_display' => $invoice->due_date?->format($dateFormat) ?? '--',
                        'paid_date_display' => $paidDate?->format($dateFormat) ?? '--',
                        'amount' => $invoiceAmount,
                        'paid_amount' => $paidAmount,
                        'remaining_amount' => $remainingAmount,
                        'is_paid' => $isPaid,
                        'is_partially_paid' => $isPartiallyPaid,
                        'status' => $statusKey,
                        'status_label' => $statusLabel,
                        'routes' => [
                            'pay' => route('admin.expenses.invoices.pay', $invoice),
                        ],
                    ];
                })->values()->all(),
                'links' => $this->paginatorLinks($invoices),
            ],
        ];
    }

    /**
     * @return array<int, array{url:?string,label:string,active:bool}>
     */
    private function paginatorLinks(LengthAwarePaginator $paginator): array
    {
        return $paginator->linkCollection()->map(function ($link) {
            return [
                'url' => $link['url'] ?? null,
                'label' => (string) ($link['label'] ?? ''),
                'active' => (bool) ($link['active'] ?? false),
            ];
        })->values()->all();
    }

    private function resolveNextDueDisplay(RecurringExpense $recurringExpense, string $dateFormat): string
    {
        if (! empty($recurringExpense->next_due_date)) {
            try {
                return Carbon::parse((string) $recurringExpense->next_due_date)->format($dateFormat);
            } catch (\Throwable $e) {
                return (string) $recurringExpense->next_due_date;
            }
        }

        return $recurringExpense->next_run_date?->format($dateFormat) ?? '--';
    }
}
