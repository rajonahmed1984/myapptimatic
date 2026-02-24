<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\RecurringExpense;
use App\Models\Setting;
use App\Services\ExpenseEntryService;
use App\Services\ExpenseInvoiceService;
use App\Support\Currency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ExpenseController extends Controller
{
    public function index(
        Request $request,
        ExpenseEntryService $entryService
    ): View|InertiaResponse {
        $sourceFilters = $request->input('sources', []);
        if (! is_array($sourceFilters) || empty($sourceFilters)) {
            $sourceFilters = ['manual', 'salary', 'contract_payout', 'sales_payout'];
        }

        [$personType, $personId] = $entryService->parsePersonFilter($request->query('person'));

        $filters = [
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'category_id' => $request->query('category_id'),
            'type' => $request->query('type'),
            'recurring_expense_id' => $request->query('recurring_expense_id'),
            'sources' => $sourceFilters,
            'person_type' => $personType,
            'person_id' => $personId,
            'person' => $request->query('person'),
        ];

        $entries = $entryService->entries($filters);

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $entries = $entries->filter(fn ($entry) => $this->entryMatchesSearch($entry, $search));
        }

        $entries = $entries->sortByDesc(fn ($entry) => $entry['expense_date'] ?? now());

        $expenses = $this->paginateEntries($entries, 20, $request);

        $totalAmount = (float) $entries->sum('amount');

        $categoryTotals = $entries
            ->groupBy('category_id')
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'category_id' => $first['category_id'],
                    'name' => $first['category_name'],
                    'total' => (float) collect($items)->sum('amount'),
                ];
            })
            ->values()
            ->sortByDesc('total');

        $now = now();
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $yearStart = $now->copy()->startOfYear()->toDateString();

        $monthlyTotal = (float) $entryService->entries([
            'start_date' => $monthStart,
            'end_date' => $now->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ])->sum('amount');

        $yearlyTotal = (float) $entryService->entries([
            'start_date' => $yearStart,
            'end_date' => $now->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ])->sum('amount');

        $topCategories = $entryService->entries([
            'start_date' => $monthStart,
            'end_date' => $now->toDateString(),
            'sources' => ['manual', 'salary', 'contract_payout', 'sales_payout'],
        ])->groupBy('category_id')
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'category_id' => $first['category_id'],
                    'name' => $first['category_name'],
                    'total' => (float) collect($items)->sum('amount'),
                ];
            })
            ->values()
            ->sortByDesc('total')
            ->take(5);

        $categories = ExpenseCategory::query()->orderBy('name')->get();
        $recurringTemplates = RecurringExpense::query()->orderBy('title')->get();
        $peopleOptions = $entryService->peopleOptions();

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }
        $currencySymbol = Currency::symbol($currencyCode);
        $formatCurrency = function ($amount) use ($currencyCode) {
            $formatted = number_format((float) ($amount ?? 0), 2);

            return "{$currencyCode} {$formatted}";
        };

        $payload = [
            'expenses' => $expenses,
            'categories' => $categories,
            'recurringTemplates' => $recurringTemplates,
            'peopleOptions' => $peopleOptions,
            'filters' => $filters,
            'totalAmount' => $totalAmount,
            'categoryTotals' => $categoryTotals,
            'monthlyTotal' => $monthlyTotal,
            'yearlyTotal' => $yearlyTotal,
            'topCategories' => $topCategories,
            'search' => $search,
            'currencySymbol' => $currencySymbol,
            'currencyCode' => $currencyCode,
            'formatCurrency' => $formatCurrency,
        ];

        if ($request->header('HX-Request')) {
            return view('admin.expenses.partials.table', $payload);
        }

        return Inertia::render(
            'Admin/Expenses/Index',
            $this->indexInertiaProps($expenses, $search, $currencyCode)
        );
    }

    public function create(ExpenseInvoiceService $invoiceService): InertiaResponse
    {
        $invoiceService->syncOverdueStatuses();

        $categories = ExpenseCategory::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $oneTimeExpenses = Expense::query()
            ->with([
                'category',
                'invoice' => function ($query) {
                    $query->withSum('payments', 'amount');
                },
            ])
            ->where('type', 'one_time')
            ->latest('expense_date')
            ->latest('id')
            ->limit(15)
            ->get();

        $currencyCode = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currencyCode)) {
            $currencyCode = Currency::DEFAULT;
        }

        $paymentMethods = PaymentMethod::dropdownOptions();

        $oneTimeItems = $oneTimeExpenses->map(function (Expense $expense) use ($currencyCode) {
            $invoice = $expense->invoice;
            $invoiceAmount = round((float) ($invoice->amount ?? 0), 2, PHP_ROUND_HALF_UP);
            $paidAmount = round((float) ($invoice->payments_sum_amount ?? 0), 2, PHP_ROUND_HALF_UP);
            if (($invoice->status ?? '') === 'paid' && $paidAmount <= 0) {
                $paidAmount = $invoiceAmount;
            }
            $remainingAmount = round(max(0, $invoiceAmount - $paidAmount), 2, PHP_ROUND_HALF_UP);
            $invoicePaid = (bool) ($invoice && $remainingAmount <= 0.009);
            $invoicePartiallyPaid = (bool) ($invoice && $paidAmount > 0 && ! $invoicePaid);
            $paymentStatus = $invoicePaid ? 'Paid' : ($invoicePartiallyPaid ? 'Partial' : 'Due');
            $paymentStatusClass = $invoicePaid
                ? 'bg-emerald-50 text-emerald-700'
                : ($invoicePartiallyPaid ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700');

            return [
                'id' => $expense->id,
                'date_label' => $expense->expense_date?->format('d-M-Y'),
                'title' => $expense->title,
                'category_name' => $expense->category?->name ?? 'N/A',
                'amount' => (float) $expense->amount,
                'invoice' => $invoice ? [
                    'invoice_no' => $invoice->invoice_no,
                    'total' => $invoiceAmount,
                    'paid' => $paidAmount,
                    'remaining' => $remainingAmount,
                    'is_paid' => $invoicePaid,
                    'is_partial' => $invoicePartiallyPaid,
                    'payment_status' => $paymentStatus,
                    'payment_status_class' => $paymentStatusClass,
                    'routes' => [
                        'pay' => route('admin.expenses.invoices.pay', $invoice),
                    ],
                ] : null,
                'routes' => [
                    'edit' => route('admin.expenses.edit', $expense),
                    'destroy' => route('admin.expenses.destroy', $expense),
                    'generate_invoice' => route('admin.expenses.invoices.store'),
                ],
                'payment_currency' => $currencyCode,
            ];
        })->values();

        return Inertia::render('Admin/Expenses/Create', [
            'pageTitle' => 'Add Expense',
            'currencyCode' => $currencyCode,
            'categories' => $categories->map(fn (ExpenseCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values(),
            'oneTimeExpenses' => $oneTimeItems,
            'paymentMethods' => $paymentMethods->map(fn ($method) => [
                'code' => $method->code,
                'name' => $method->name,
            ])->values(),
            'form' => [
                'category_id' => old('category_id'),
                'title' => old('title', ''),
                'amount' => old('amount', ''),
                'expense_date' => old('expense_date', now()->toDateString()),
                'notes' => old('notes', ''),
                'generate_invoice' => (bool) old('generate_invoice', false),
            ],
            'routes' => [
                'index' => route('admin.expenses.index'),
                'store' => route('admin.expenses.store'),
                'invoice_store' => route('admin.expenses.invoices.store'),
            ],
        ]);
    }

    public function edit(Expense $expense): InertiaResponse
    {
        if ($expense->type !== 'one_time') {
            abort(404);
        }

        $categories = ExpenseCategory::query()
            ->where('status', 'active')
            ->orWhere('id', $expense->category_id)
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Expenses/Edit', [
            'pageTitle' => 'Edit Expense',
            'categories' => $categories->map(fn (ExpenseCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
            ])->values(),
            'expense' => [
                'id' => $expense->id,
                'category_id' => $expense->category_id,
                'title' => $expense->title,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date?->toDateString(),
                'notes' => $expense->notes,
                'attachment_url' => $expense->attachment_path
                    ? route('admin.expenses.attachments.show', $expense)
                    : null,
            ],
            'routes' => [
                'back' => route('admin.expenses.create'),
                'update' => route('admin.expenses.update', $expense),
            ],
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        if ($expense->type !== 'one_time') {
            abort(404);
        }

        $data = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where('status', 'active'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $expense->update([
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'notes' => $data['notes'] ?? null,
        ]);

        if ($request->hasFile('attachment')) {
            if ($expense->attachment_path && Storage::disk('public')->exists($expense->attachment_path)) {
                Storage::disk('public')->delete($expense->attachment_path);
            }

            $path = $request->file('attachment')->store('expenses/receipts', 'public');
            $expense->update(['attachment_path' => $path]);
        }

        return redirect()->route('admin.expenses.create')
            ->with('status', 'Expense updated.');
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        if ($expense->type !== 'one_time') {
            abort(404);
        }

        if ($expense->attachment_path && Storage::disk('public')->exists($expense->attachment_path)) {
            Storage::disk('public')->delete($expense->attachment_path);
        }

        $expense->delete();

        return redirect()->route('admin.expenses.create')
            ->with('status', 'Expense deleted.');
    }

    public function store(Request $request, ExpenseInvoiceService $invoiceService): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => [
                'required',
                Rule::exists('expense_categories', 'id')->where('status', 'active'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'generate_invoice' => ['nullable', 'boolean'],
        ]);

        $expense = Expense::create([
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'],
            'notes' => $data['notes'] ?? null,
            'type' => 'one_time',
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('expenses/receipts', 'public');
            $expense->update(['attachment_path' => $path]);
        }

        if ($request->boolean('generate_invoice')) {
            $invoiceService->createForExpense($expense, $request->user()->id);
        }

        return redirect()->route('admin.expenses.index')
            ->with('status', 'Expense recorded.');
    }

    public function attachment(Expense $expense)
    {
        if (! $expense->attachment_path) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($expense->attachment_path)) {
            abort(404);
        }

        return Storage::disk('public')->response($expense->attachment_path);
    }

    private function paginateEntries(Collection $entries, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $entries = $entries->values();
        $slice = $entries->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $entries->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function entryMatchesSearch($entry, string $search): bool
    {
        $haystacks = [
            (string) data_get($entry, 'title'),
            (string) data_get($entry, 'notes'),
            (string) data_get($entry, 'category_name'),
            (string) data_get($entry, 'person_name'),
            (string) data_get($entry, 'source_label'),
            (string) data_get($entry, 'source_detail'),
            (string) data_get($entry, 'invoice_no'),
        ];

        foreach ($haystacks as $value) {
            if ($value !== '' && stripos($value, $search) !== false) {
                return true;
            }
        }

        return false;
    }

    private function indexInertiaProps(LengthAwarePaginator $expenses, string $search, string $currencyCode): array
    {
        $globalDateFormat = (string) config('app.date_format', 'd-m-Y');

        $expenseRows = $expenses->getCollection()
            ->values()
            ->map(function ($entry) use ($currencyCode, $globalDateFormat) {
                $sourceType = (string) data_get($entry, 'source_type');
                $sourceId = data_get($entry, 'source_id');
                $attachmentPath = (string) data_get($entry, 'attachment_path');

                $canViewAttachment = $sourceType === 'expense'
                    && $attachmentPath !== ''
                    && is_numeric($sourceId);

                return [
                    'key' => (string) data_get($entry, 'key', ''),
                    'invoice_no' => (string) (data_get($entry, 'invoice_no') ?: '--'),
                    'expense_date_display' => $this->formatEntryDate(data_get($entry, 'expense_date'), $globalDateFormat),
                    'title' => (string) data_get($entry, 'title', '--'),
                    'notes' => (string) (data_get($entry, 'notes') ?: ''),
                    'category_name' => (string) (data_get($entry, 'category_name') ?: '--'),
                    'person_name' => (string) (data_get($entry, 'person_name') ?: '--'),
                    'amount_display' => $currencyCode.' '.number_format((float) data_get($entry, 'amount', 0), 2),
                    'attachment_url' => $canViewAttachment
                        ? route('admin.expenses.attachments.show', (int) $sourceId)
                        : null,
                ];
            })
            ->all();

        $paginationLinks = $expenses->linkCollection()
            ->map(function ($link) {
                return [
                    'url' => $link['url'],
                    'label' => (string) $link['label'],
                    'active' => (bool) $link['active'],
                ];
            })
            ->all();

        return [
            'pageTitle' => 'Expenses',
            'search' => $search,
            'routes' => [
                'index' => route('admin.expenses.index'),
                'recurring' => route('admin.expenses.recurring.index'),
                'categories' => route('admin.expenses.categories.index'),
                'create' => route('admin.expenses.create'),
            ],
            'expenses' => $expenseRows,
            'pagination_links' => $paginationLinks,
        ];
    }

    private function formatEntryDate(mixed $value, string $format): string
    {
        if (empty($value)) {
            return '--';
        }

        try {
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Throwable) {
            return '--';
        }
    }
}
