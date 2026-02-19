<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\ExpenseInvoice;
use App\Models\PaymentMethod;
use App\Models\RecurringExpense;
use App\Services\ExpenseInvoiceService;
use App\Services\RecurringExpenseGenerator;
use App\Support\Currency;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecurringExpenseController extends Controller
{
    public function index(ExpenseInvoiceService $invoiceService): View
    {
        $invoiceService->syncOverdueStatuses();

        $recurringExpenses = RecurringExpense::query()
            ->with('category')
            ->orderByDesc('next_run_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.expenses.recurring.index', compact('recurringExpenses'));
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

    public function show(RecurringExpense $recurringExpense, ExpenseInvoiceService $invoiceService): View
    {
        $recurringExpense->load('category');
        $invoiceService->syncOverdueStatuses($recurringExpense->id);

        $baseInvoices = ExpenseInvoice::query()
            ->where('source_type', 'expense')
            ->whereHas('expense', function ($query) use ($recurringExpense) {
                $query->where('recurring_expense_id', $recurringExpense->id);
            });

        $invoices = (clone $baseInvoices)
            ->with('expense')
            ->withSum('payments', 'amount')
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

        return view('admin.expenses.recurring.show', [
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
        ]);
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

    public function pause(RecurringExpense $recurringExpense): RedirectResponse
    {
        $recurringExpense->update(['status' => 'paused']);

        return back()->with('status', 'Recurring expense paused.');
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

    public function generate(Request $request, RecurringExpenseGenerator $generator): RedirectResponse
    {
        $templateId = $request->input('template_id');
        $result = $generator->generate(now(), $templateId ? (int) $templateId : null);

        return back()->with('status', "Generated {$result['created']} expense occurrence(s).");
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
}
