<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Services\CommissionService;
use App\Support\AjaxResponse;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AccountingController extends Controller
{
    private const TYPES = ['payment', 'refund', 'credit', 'expense'];

    private const PER_PAGE = 30;

    private const LOOKUP_LIMIT = 20;

    public function index(Request $request): InertiaResponse
    {
        $scope = 'ledger';
        $pageTitle = 'Ledger';
        $search = trim((string) $request->input('search', ''));
        $payload = $this->indexPayload($scope, $pageTitle, $search);

        return Inertia::render(
            'Admin/Accounting/Index',
            $this->indexInertiaProps($payload['entries'], $scope, $search, $pageTitle, url()->current())
        );
    }

    public function transactions(Request $request): InertiaResponse
    {
        $scope = 'transactions';
        $pageTitle = 'Transactions';
        $search = trim((string) $request->input('search', ''));
        $payload = $this->indexPayload($scope, $pageTitle, $search);

        return Inertia::render(
            'Admin/Accounting/Index',
            $this->indexInertiaProps($payload['entries'], $scope, $search, $pageTitle, url()->current())
        );
    }

    public function create(Request $request): InertiaResponse
    {
        $type = $this->normalizeType($request->query('type', 'payment'));
        $scope = $this->normalizeScope($request->query('scope', 'ledger'));
        $search = trim((string) $request->query('search', ''));
        $selectedInvoice = $request->query('invoice_id')
            ? Invoice::query()->with('customer')->find($request->query('invoice_id'))
            : null;

        return Inertia::render(
            'Admin/Accounting/Form',
            $this->formInertiaProps(
                null,
                $type,
                $scope,
                $search,
                $this->formData($type, $selectedInvoice)
            )
        );
    }

    public function customerOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->select(['id', 'name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->limit(self::LOOKUP_LIMIT)
            ->get()
            ->map(fn (Customer $customer) => [
                'value' => (string) $customer->id,
                'label' => (string) $customer->name,
            ])
            ->values();

        return response()->json(['data' => $customers]);
    }

    public function invoiceOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $invoices = Invoice::query()
            ->select(['id', 'number', 'customer_id', 'status', 'issue_date', 'due_date', 'total'])
            ->with('customer:id,name')
            ->withSum([
                'accountingEntries as paid_amount' => fn (Builder $query) => $query->where('type', 'payment'),
            ], 'amount')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $lookup) use ($search): void {
                    $lookup->where('number', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$search.'%'));

                    if (is_numeric($search)) {
                        $lookup->orWhere('id', (int) $search);
                    }
                });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(self::LOOKUP_LIMIT)
            ->get()
            ->map(fn (Invoice $invoice) => $this->invoiceLookupRow(
                $invoice,
                (float) ($invoice->paid_amount ?? 0)
            ))
            ->values();

        return response()->json(['data' => $invoices]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        try {
            [$data, $invoice] = $this->validateEntry($request);
        } catch (ValidationException $exception) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxValidation($exception->errors());
            }

            throw $exception;
        }

        $data['created_by'] = $request->user()->id;
        $entry = AccountingEntry::create($data);

        if ($entry->type === 'payment' && $invoice) {
            if ((float) $entry->amount >= (float) $invoice->total) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => $invoice->paid_at ?? Carbon::now(),
                ]);
            }
        }

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger')))),
                'Accounting entry added.'
            );
        }

        return redirect()->route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger'))))
            ->with('status', 'Accounting entry added.');
    }

    public function edit(Request $request, AccountingEntry $entry): InertiaResponse
    {
        $scope = $this->normalizeScope($request->query('scope', 'ledger'));
        $search = trim((string) $request->query('search', ''));

        return Inertia::render(
            'Admin/Accounting/Form',
            $this->formInertiaProps(
                $entry,
                $entry->type,
                $scope,
                $search,
                $this->formData($entry->type, $entry->invoice, $entry)
            )
        );
    }

    public function update(Request $request, AccountingEntry $entry): RedirectResponse|JsonResponse
    {
        try {
            [$data, $invoice] = $this->validateEntry($request);
        } catch (ValidationException $exception) {
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxValidation($exception->errors());
            }

            throw $exception;
        }

        $entry->update($data);

        if ($entry->type === 'payment' && $invoice) {
            if ((float) $entry->amount >= (float) $invoice->total) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => $invoice->paid_at ?? Carbon::now(),
                ]);
            }
        }

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger')))),
                'Accounting entry updated.'
            );
        }

        return redirect()->route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger'))))
            ->with('status', 'Accounting entry updated.');
    }

    public function destroy(
        Request $request,
        AccountingEntry $entry,
        CommissionService $commissionService
    ): RedirectResponse|JsonResponse {
        $invoiceId = $entry->invoice_id;
        DB::transaction(function () use ($entry, $invoiceId, $commissionService): void {
            $entry->delete();

            if ($invoiceId) {
                $invoice = Invoice::query()->lockForUpdate()->find($invoiceId);
                if ($invoice) {
                    $paidTotal = (float) AccountingEntry::where('invoice_id', $invoiceId)
                        ->where('type', 'payment')
                        ->sum('amount');
                    $creditTotal = (float) AccountingEntry::where('invoice_id', $invoiceId)
                        ->where('type', 'credit')
                        ->sum('amount');

                    $outstanding = max(0.0, (float) $invoice->total - $creditTotal - $paidTotal);

                    if ($outstanding > 0.009) {
                        $isOverdue = $invoice->due_date && $invoice->due_date->isPast();
                        $invoice->update([
                            'status' => $isOverdue ? 'overdue' : 'unpaid',
                            'paid_at' => null,
                        ]);
                        $commissionService->reverseEarningsOnRefund($invoice);
                    } else {
                        $invoice->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);
                    }
                }
            }
        });

        if ($request->input('redirect_back') || $request->header('referer')) {
            $targetUrl = $request->header('referer') ?: route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger'))));
            if (AjaxResponse::ajaxFromRequest($request)) {
                return AjaxResponse::ajaxRedirect($targetUrl, 'Accounting entry deleted.');
            }

            return redirect()->to($targetUrl)->with('status', 'Accounting entry deleted.');
        }

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger')))),
                'Accounting entry deleted.'
            );
        }

        return redirect()->route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger'))))
            ->with('status', 'Accounting entry deleted.');
    }

    private function indexPayload(string $scope, string $pageTitle, string $search): array
    {
        return [
            'entries' => $this->entriesForScope($scope, $search),
            'pageTitle' => $pageTitle,
            'scope' => $scope,
            'search' => $search,
        ];
    }

    private function baseEntriesQuery(?array $types, string $search): Builder
    {
        $query = AccountingEntry::query();

        if ($types) {
            $query->whereIn('type', $types);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('reference', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                        $invoiceQuery->where('number', 'like', '%'.$search.'%')
                            ->orWhere('id', 'like', '%'.$search.'%');
                    })
                    ->orWhereHas('paymentGateway', function ($gatewayQuery) use ($search) {
                        $gatewayQuery->where('name', 'like', '%'.$search.'%');
                    });

                if (is_numeric($search)) {
                    $inner->orWhere('id', (int) $search)
                        ->orWhere('amount', (float) $search);
                }
            });
        }

        return $query;
    }

    private function queryEntries(?array $types, string $search): Builder
    {
        return $this->baseEntriesQuery($types, $search)
            ->with(['customer', 'invoice', 'paymentGateway'])
            ->latest('entry_date')
            ->latest('id');
    }

    private function entriesForScope(string $scope, string $search): LengthAwarePaginator
    {
        return $this->queryEntries($this->scopeTypes($scope), $search)
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    private function normalizeScope(string $scope): string
    {
        return in_array($scope, ['ledger', 'transactions'], true)
            ? $scope
            : 'ledger';
    }

    private function scopeTypes(string $scope): ?array
    {
        return match ($scope) {
            'transactions' => ['payment', 'refund'],
            default => null,
        };
    }

    private function scopeRoute(string $scope): string
    {
        return match ($scope) {
            'transactions' => 'admin.accounting.transactions',
            default => 'admin.accounting.ledger',
        };
    }

    private function formData(string $type, ?Invoice $selectedInvoice = null, ?AccountingEntry $entry = null): array
    {
        $selectedInvoice?->loadMissing('customer:id,name,email');

        $dueAmount = null;
        $invoicePaidMap = [];
        if ($selectedInvoice) {
            $paidAmount = AccountingEntry::query()
                ->where('invoice_id', $selectedInvoice->id)
                ->where('type', 'payment')
                ->sum('amount');
            $dueAmount = max(0, $selectedInvoice->total - $paidAmount);
            $invoicePaidMap[$selectedInvoice->id] = (float) $paidAmount;
        }

        $selectedCustomerIds = collect([
            $entry?->customer_id,
            $selectedInvoice?->customer_id,
        ])->filter()->unique()->values();

        $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currency)) {
            $currency = Currency::DEFAULT;
        }

        return [
            'entry' => $entry,
            'type' => $type,
            'selectedInvoice' => $selectedInvoice,
            'dueAmount' => $dueAmount,
            'invoicePaidMap' => $invoicePaidMap,
            'customers' => Customer::query()
                ->whereIn('id', $selectedCustomerIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'invoices' => $selectedInvoice ? collect([$selectedInvoice]) : collect(),
            'gateways' => PaymentGateway::query()->orderBy('sort_order')->get(['id', 'name']),
            'currency' => $currency,
        ];
    }

    private function invoiceLookupRow(Invoice $invoice, float $paidAmount): array
    {
        return [
            'value' => (string) $invoice->id,
            'label' => sprintf(
                '%s - %s',
                (string) ($invoice->number ?? $invoice->id),
                (string) ($invoice->customer?->name ?? '--')
            ),
            'id' => $invoice->id,
            'invoice_label' => (string) ($invoice->number ?? $invoice->id),
            'customer_name' => (string) ($invoice->customer?->name ?? '--'),
            'customer_id' => (string) ($invoice->customer_id ?? ''),
            'status' => (string) ($invoice->status ?? ''),
            'issue_date' => $invoice->issue_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
            'due_date' => $invoice->due_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
            'total_amount' => (float) ($invoice->total ?? 0),
            'due_amount' => max(0, (float) ($invoice->total ?? 0) - $paidAmount),
        ];
    }

    private function validateEntry(Request $request): array
    {
        $type = $this->normalizeType($request->input('type', 'payment'));
        $request->merge(['type' => $type]);

        $rules = [
            'type' => ['required', Rule::in(self::TYPES)],
            'entry_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3', Rule::in(Currency::allowed())],
            'description' => ['nullable', 'string'],
            'reference' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'payment_gateway_id' => ['nullable', 'exists:payment_gateways,id'],
        ];

        if ($type === 'payment') {
            $rules['customer_id'] = ['nullable', 'exists:customers,id'];
            $rules['invoice_id'] = ['required', 'exists:invoices,id'];
        } elseif ($type === 'refund') {
            $rules['customer_id'] = ['required_without:invoice_id', 'exists:customers,id'];
        } elseif ($type === 'credit') {
            $rules['customer_id'] = ['required', 'exists:customers,id'];
        }

        $data = $request->validate($rules);
        $data['type'] = $type;
        $data['currency'] = strtoupper($data['currency']);

        $invoice = null;

        if (! empty($data['invoice_id'])) {
            $invoice = Invoice::query()->find($data['invoice_id']);

            if ($invoice) {
                if (! empty($data['customer_id']) && (int) $data['customer_id'] !== $invoice->customer_id) {
                    throw ValidationException::withMessages([
                        'invoice_id' => 'Selected invoice does not belong to the customer.',
                    ]);
                }

                $data['customer_id'] = $invoice->customer_id;
            }
        }

        if (! in_array($type, ['payment', 'refund'], true)) {
            $data['payment_gateway_id'] = null;
        }

        return [$data, $invoice];
    }

    private function normalizeType(string $type): string
    {
        return in_array($type, self::TYPES, true) ? $type : 'payment';
    }

    private function indexInertiaProps(
        LengthAwarePaginator $paginator,
        string $scope,
        string $search,
        string $pageTitle,
        string $searchAction
    ): array {
        $dateFormat = config('app.date_format', 'd-m-Y');
        $entries = collect($paginator->items())->values();
        $aggregateRows = $this->accountingAggregateRows($scope, $search);
        $runningBalances = $this->runningBalancesForPage($entries, $scope, $search);

        $currencySummary = $aggregateRows
            ->groupBy(function ($row) {
                return strtoupper((string) $row->currency_code);
            })
            ->map(function (Collection $group, string $currency) {
                $inflow = (float) $group
                    ->reject(fn ($row) => $this->isOutflowType((string) $row->type))
                    ->sum('total_amount');
                $outflow = (float) $group
                    ->filter(fn ($row) => $this->isOutflowType((string) $row->type))
                    ->sum('total_amount');
                $net = $inflow - $outflow;

                return [
                    'currency' => $currency,
                    'entries_count' => (int) $group->sum('entries_count'),
                    'inflow_display' => sprintf('%s %s', $currency, number_format($inflow, 2)),
                    'outflow_display' => sprintf('%s %s', $currency, number_format($outflow, 2)),
                    'net_display' => sprintf('%s %s', $currency, number_format(abs($net), 2)),
                    'net_is_negative' => $net < 0,
                ];
            })
            ->values()
            ->all();

        $summaryTypes = $scope === 'transactions'
            ? ['payment', 'refund']
            : self::TYPES;

        $typeSummary = collect($summaryTypes)->map(function (string $type) use ($aggregateRows) {
            $group = $aggregateRows->where('type', $type)->values();
            $currencyTotals = $group
                ->groupBy(function ($row) {
                    return strtoupper((string) $row->currency_code);
                })
                ->map(function (Collection $currencyGroup, string $currency) {
                    return sprintf('%s %s', $currency, number_format((float) $currencyGroup->sum('total_amount'), 2));
                })
                ->values()
                ->all();

            return [
                'type' => $type,
                'label' => ucfirst(str_replace('_', ' ', $type)),
                'count' => (int) $group->sum('entries_count'),
                'is_outflow' => $this->isOutflowType($type),
                'totals' => $currencyTotals,
            ];
        })->values()->all();

        $inflowEntries = (int) $aggregateRows
            ->reject(fn ($row) => $this->isOutflowType((string) $row->type))
            ->sum('entries_count');
        $outflowEntries = (int) $aggregateRows
            ->filter(fn ($row) => $this->isOutflowType((string) $row->type))
            ->sum('entries_count');
        $latestEntryDate = $aggregateRows->max('latest_entry_date');

        return [
            'pageTitle' => $pageTitle,
            'scope' => $scope,
            'search' => $search,
            'searchAction' => $searchAction,
            'routes' => [
                'ledger' => route('admin.accounting.ledger'),
                'transactions' => route('admin.accounting.transactions'),
                'create' => [
                    'payment' => route('admin.accounting.create', ['type' => 'payment', 'scope' => $scope, 'search' => $search]),
                    'refund' => route('admin.accounting.create', ['type' => 'refund', 'scope' => $scope, 'search' => $search]),
                    'credit' => route('admin.accounting.create', ['type' => 'credit', 'scope' => $scope, 'search' => $search]),
                    'expense' => route('admin.accounting.create', ['type' => 'expense', 'scope' => $scope, 'search' => $search]),
                ],
            ],
            'summary' => [
                'total_entries' => $paginator->total(),
                'inflow_entries' => $inflowEntries,
                'outflow_entries' => $outflowEntries,
                'latest_entry_date_display' => $latestEntryDate
                    ? Carbon::parse($latestEntryDate)->format($dateFormat)
                    : '--',
                'currencies' => $currencySummary,
                'types' => $typeSummary,
            ],
            'entries' => $entries->map(function (AccountingEntry $entry) use ($scope, $search, $dateFormat, $runningBalances) {
                $amount = number_format((float) $entry->amount, 2);
                $isOutflow = $entry->isOutflow();
                $currency = strtoupper((string) $entry->currency);

                return [
                    'id' => $entry->id,
                    'type' => (string) $entry->type,
                    'entry_date_display' => $entry->entry_date?->format($dateFormat) ?? '--',
                    'entry_date_iso' => $entry->entry_date?->toDateString(),
                    'type_label' => ucfirst((string) $entry->type),
                    'customer_name' => $entry->customer?->name ?? '-',
                    'invoice_label' => $entry->invoice?->number ?? (string) ($entry->invoice?->id ?? '-'),
                    'gateway_name' => $entry->paymentGateway?->name ?? '-',
                    'description' => (string) ($entry->description ?: '-'),
                    'amount_display' => sprintf('%s%s %s', $isOutflow ? '-' : '+', strtoupper((string) $entry->currency), $amount),
                    'is_outflow' => $isOutflow,
                    'currency' => $currency,
                    'amount_value' => (float) $entry->amount,
                    'running_balance_display' => sprintf(
                        '%s %s',
                        $currency,
                        number_format(abs((float) ($runningBalances[$entry->id] ?? 0)), 2)
                    ),
                    'running_balance_is_negative' => ((float) ($runningBalances[$entry->id] ?? 0)) < 0,
                    'reference' => $entry->reference ?: '-',
                    'routes' => [
                        'customer_show' => $entry->customer ? route('admin.customers.show', $entry->customer) : null,
                        'invoice_show' => $entry->invoice ? route('admin.invoices.show', $entry->invoice) : null,
                        'edit' => route('admin.accounting.edit', ['entry' => $entry, 'scope' => $scope, 'search' => $search]),
                        'destroy' => route('admin.accounting.destroy', $entry),
                    ],
                ];
            })->values()->all(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'previous_url' => $paginator->previousPageUrl(),
                'next_url' => $paginator->nextPageUrl(),
                'has_pages' => $paginator->hasPages(),
            ],
        ];
    }

    private function accountingAggregateRows(string $scope, string $search): Collection
    {
        return $this->baseEntriesQuery($this->scopeTypes($scope), $search)
            ->selectRaw(
                'UPPER(currency) as currency_code, type, COUNT(*) as entries_count, '
                .'SUM(amount) as total_amount, MAX(entry_date) as latest_entry_date'
            )
            ->groupByRaw('UPPER(currency), type')
            ->get();
    }

    /**
     * @param  Collection<int, AccountingEntry>  $entries
     * @return array<int, float>
     */
    private function runningBalancesForPage(Collection $entries, string $scope, string $search): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        $ascendingEntries = $entries
            ->sortBy([
                ['entry_date', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
        $oldestEntry = $ascendingEntries->first();

        $balancesByCurrency = $this->baseEntriesQuery($this->scopeTypes($scope), $search)
            ->where(function (Builder $query) use ($oldestEntry): void {
                $query->whereDate('entry_date', '<', $oldestEntry->entry_date->toDateString())
                    ->orWhere(function (Builder $sameDate) use ($oldestEntry): void {
                        $sameDate->whereDate('entry_date', $oldestEntry->entry_date->toDateString())
                            ->where('id', '<', $oldestEntry->id);
                    });
            })
            ->selectRaw(
                'UPPER(currency) as currency_code, '
                ."SUM(CASE WHEN type IN ('refund', 'credit', 'expense') THEN -amount ELSE amount END) as balance"
            )
            ->groupByRaw('UPPER(currency)')
            ->pluck('balance', 'currency_code')
            ->map(fn ($balance) => (float) $balance)
            ->all();

        $runningBalances = [];

        $ascendingEntries->each(function (AccountingEntry $entry) use (&$runningBalances, &$balancesByCurrency): void {
            $currency = strtoupper((string) $entry->currency);
            $delta = $entry->isOutflow() ? -(float) $entry->amount : (float) $entry->amount;

            $balancesByCurrency[$currency] = ($balancesByCurrency[$currency] ?? 0) + $delta;
            $runningBalances[$entry->id] = $balancesByCurrency[$currency];
        });

        return $runningBalances;
    }

    private function isOutflowType(string $type): bool
    {
        return in_array($type, ['refund', 'credit', 'expense'], true);
    }

    private function formInertiaProps(
        ?AccountingEntry $entry,
        string $type,
        string $scope,
        string $search,
        array $formData
    ): array {
        $isEdit = $entry !== null;
        $selectedInvoice = $formData['selectedInvoice'] ?? null;
        $invoicePaidMap = $formData['invoicePaidMap'] ?? [];
        $selectedInvoiceLabel = $selectedInvoice ? (string) ($selectedInvoice->number ?? $selectedInvoice->id) : '';
        $defaultAmount = (string) old(
            'amount',
            (string) ($entry?->amount ?? (
                $type === 'payment' && $selectedInvoice
                    ? number_format((float) ($formData['dueAmount'] ?? 0), 2, '.', '')
                    : ''
            ))
        );
        $defaultReference = (string) old(
            'reference',
            (string) ($entry?->reference ?? ($type === 'payment' && $selectedInvoice ? $selectedInvoiceLabel : ''))
        );
        $defaultDescription = (string) old(
            'description',
            (string) ($entry?->description ?? ($type === 'payment' && $selectedInvoice ? "Payment for Invoice #{$selectedInvoiceLabel}" : ''))
        );

        return [
            'pageTitle' => $isEdit ? 'Edit Accounting Entry' : 'Add Accounting Entry',
            'is_edit' => $isEdit,
            'scope' => $scope,
            'search' => $search,
            'types' => self::TYPES,
            'customers' => collect($formData['customers'] ?? [])->map(function (Customer $customer) {
                return [
                    'id' => $customer->id,
                    'name' => (string) $customer->name,
                ];
            })->values()->all(),
            'invoices' => collect($formData['invoices'] ?? [])->map(function (Invoice $invoice) use ($invoicePaidMap) {
                $paidAmount = (float) ($invoicePaidMap[$invoice->id] ?? 0);
                $dueAmount = max(0, (float) $invoice->total - $paidAmount);

                return [
                    'id' => $invoice->id,
                    'label' => (string) ($invoice->number ?? $invoice->id),
                    'customer_name' => (string) ($invoice->customer?->name ?? '--'),
                    'customer_id' => (string) ($invoice->customer_id ?? ''),
                    'status' => (string) ($invoice->status ?? ''),
                    'issue_date' => $invoice->issue_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'due_date' => $invoice->due_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'total_amount' => (float) ($invoice->total ?? 0),
                    'due_amount' => $dueAmount,
                ];
            })->values()->all(),
            'gateways' => collect($formData['gateways'] ?? [])->map(function (PaymentGateway $gateway) {
                return [
                    'id' => $gateway->id,
                    'name' => (string) $gateway->name,
                ];
            })->values()->all(),
            'form' => [
                'action' => $isEdit
                    ? route('admin.accounting.update', $entry)
                    : route('admin.accounting.store'),
                'method' => $isEdit ? 'PUT' : 'POST',
                'fields' => [
                    'type' => (string) old('type', $type),
                    'entry_date' => (string) old('entry_date', (string) ($entry?->entry_date?->toDateString() ?? now()->toDateString())),
                    'amount' => $defaultAmount,
                    'currency' => (string) old('currency', (string) ($entry?->currency ?? ($formData['currency'] ?? ''))),
                    'description' => $defaultDescription,
                    'reference' => $defaultReference,
                    'customer_id' => (string) old('customer_id', (string) ($entry?->customer_id ?? ($selectedInvoice?->customer_id ?? ''))),
                    'invoice_id' => (string) old('invoice_id', (string) ($entry?->invoice_id ?? ($selectedInvoice?->id ?? ''))),
                    'payment_gateway_id' => (string) old('payment_gateway_id', (string) ($entry?->payment_gateway_id ?? '')),
                ],
                'due_amount' => $formData['dueAmount'] ?? null,
                'selected_invoice' => $selectedInvoice ? [
                    'id' => $selectedInvoice->id,
                    'label' => (string) ($selectedInvoice->number ?? $selectedInvoice->id),
                    'customer_name' => (string) ($selectedInvoice->customer?->name ?? '--'),
                    'customer_email' => (string) ($selectedInvoice->customer?->email ?? '--'),
                    'status' => (string) ($selectedInvoice->status ?? '--'),
                    'issue_date' => $selectedInvoice->issue_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'due_date' => $selectedInvoice->due_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                    'total_amount' => (float) ($selectedInvoice->total ?? 0),
                    'due_amount' => (float) ($formData['dueAmount'] ?? 0),
                ] : null,
            ],
            'routes' => [
                'index' => route($this->scopeRoute($scope)),
                'customer_options' => route('admin.accounting.lookups.customers'),
                'invoice_options' => route('admin.accounting.lookups.invoices'),
            ],
        ];
    }
}
