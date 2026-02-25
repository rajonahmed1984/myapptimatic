<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Support\AjaxResponse;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AccountingController extends Controller
{
    private const TYPES = ['payment', 'refund', 'credit', 'expense'];

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

    public function destroy(Request $request, AccountingEntry $entry): RedirectResponse|JsonResponse
    {
        $entry->delete();

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

    private function queryEntries(?array $types, string $search, ?string $scope = null)
    {
        $query = AccountingEntry::query()
            ->with(['customer', 'invoice', 'paymentGateway'])
            ->latest('entry_date')
            ->latest('id');

        if ($types) {
            $query->whereIn('type', $types);
        }

        if ($scope === 'transactions') {
            $query->where(function ($inner) {
                $inner->whereIn('type', ['payment', 'refund'])
                    ->orWhere(function ($creditQuery) {
                        $creditQuery->where('type', 'credit')
                            ->whereNotNull('invoice_id');
                    });
            });
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

    private function entriesForScope(string $scope, string $search): Collection
    {
        $entries = $this->queryEntries($this->scopeTypes($scope), $search, $scope)->get();

        if ($scope === 'transactions') {
            return $this->deduplicateCreditSettlements($entries);
        }

        return $entries;
    }

    private function deduplicateCreditSettlements(Collection $entries): Collection
    {
        return $entries->unique(function (AccountingEntry $entry) {
            if ($entry->type !== 'credit' || ! $entry->invoice_id) {
                return 'entry:'.$entry->id;
            }

            $amount = number_format((float) $entry->amount, 2, '.', '');

            return 'credit-settlement:'.$entry->invoice_id.':'.strtoupper((string) $entry->currency).':'.$amount;
        })->values();
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
            'transactions' => ['payment', 'refund', 'credit'],
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
        $dueAmount = null;
        if ($selectedInvoice) {
            $paidAmount = AccountingEntry::query()
                ->where('invoice_id', $selectedInvoice->id)
                ->where('type', 'payment')
                ->sum('amount');
            $dueAmount = max(0, $selectedInvoice->total - $paidAmount);
        }

        $currency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($currency)) {
            $currency = Currency::DEFAULT;
        }

        return [
            'entry' => $entry,
            'type' => $type,
            'selectedInvoice' => $selectedInvoice,
            'dueAmount' => $dueAmount,
            'customers' => Customer::query()->orderBy('name')->get(),
            'invoices' => Invoice::query()->with('customer')->orderByDesc('issue_date')->get(),
            'gateways' => PaymentGateway::query()->orderBy('sort_order')->get(),
            'currency' => $currency,
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
        Collection $entries,
        string $scope,
        string $search,
        string $pageTitle,
        string $searchAction
    ): array {
        $dateFormat = config('app.date_format', 'd-m-Y');

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
            'entries' => $entries->map(function (AccountingEntry $entry) use ($scope, $search, $dateFormat) {
                $amount = number_format((float) $entry->amount, 2);
                $isOutflow = $entry->isOutflow();

                return [
                    'id' => $entry->id,
                    'entry_date_display' => $entry->entry_date?->format($dateFormat) ?? '--',
                    'type_label' => ucfirst((string) $entry->type),
                    'customer_name' => $entry->customer?->name ?? '-',
                    'invoice_label' => $entry->invoice?->number ?? (string) ($entry->invoice?->id ?? '-'),
                    'gateway_name' => $entry->paymentGateway?->name ?? '-',
                    'amount_display' => sprintf('%s%s %s', $isOutflow ? '-' : '+', strtoupper((string) $entry->currency), $amount),
                    'is_outflow' => $isOutflow,
                    'reference' => $entry->reference ?: '-',
                    'routes' => [
                        'customer_show' => $entry->customer ? route('admin.customers.show', $entry->customer) : null,
                        'invoice_show' => $entry->invoice ? route('admin.invoices.show', $entry->invoice) : null,
                        'edit' => route('admin.accounting.edit', ['entry' => $entry, 'scope' => $scope, 'search' => $search]),
                        'destroy' => route('admin.accounting.destroy', $entry),
                    ],
                ];
            })->values()->all(),
        ];
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
            'invoices' => collect($formData['invoices'] ?? [])->map(function (Invoice $invoice) {
                return [
                    'id' => $invoice->id,
                    'label' => (string) ($invoice->number ?? $invoice->id),
                    'customer_name' => (string) ($invoice->customer?->name ?? '--'),
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
                    'amount' => (string) old('amount', (string) ($entry?->amount ?? '')),
                    'currency' => (string) old('currency', (string) ($entry?->currency ?? ($formData['currency'] ?? ''))),
                    'description' => (string) old('description', (string) ($entry?->description ?? '')),
                    'reference' => (string) old('reference', (string) ($entry?->reference ?? '')),
                    'customer_id' => (string) old('customer_id', (string) ($entry?->customer_id ?? '')),
                    'invoice_id' => (string) old('invoice_id', (string) ($entry?->invoice_id ?? ($selectedInvoice?->id ?? ''))),
                    'payment_gateway_id' => (string) old('payment_gateway_id', (string) ($entry?->payment_gateway_id ?? '')),
                ],
                'due_amount' => $formData['dueAmount'] ?? null,
            ],
            'routes' => [
                'index' => route($this->scopeRoute($scope)),
            ],
        ];
    }
}
