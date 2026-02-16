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
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingController extends Controller
{
    private const TYPES = ['payment', 'refund', 'credit', 'expense'];

    public function index(Request $request)
    {
        return $this->renderIndex($request, 'ledger', 'Ledger', null);
    }

    public function transactions(Request $request)
    {
        return $this->renderIndex($request, 'transactions', 'Transactions', ['payment', 'refund']);
    }

    public function refunds(Request $request)
    {
        return $this->renderIndex($request, 'refunds', 'Refunds', ['refund']);
    }

    public function credits(Request $request)
    {
        return $this->renderIndex($request, 'credits', 'Credits', ['credit']);
    }

    public function expenses(Request $request)
    {
        return $this->renderIndex($request, 'expenses', 'Expenses', ['expense']);
    }

    public function create(Request $request): View
    {
        $type = $this->normalizeType($request->query('type', 'payment'));
        $scope = $this->normalizeScope($request->query('scope', 'ledger'));
        $search = trim((string) $request->query('search', ''));
        $selectedInvoice = $request->query('invoice_id')
            ? Invoice::query()->with('customer')->find($request->query('invoice_id'))
            : null;

        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.accounting.partials.form', array_merge(
                $this->formData($type, $selectedInvoice),
                [
                    'formAction' => route('admin.accounting.store'),
                    'formMethod' => 'POST',
                    'scope' => $scope,
                    'search' => $search,
                ]
            ));
        }

        return view('admin.accounting.create', $this->formData($type, $selectedInvoice));
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
            return AjaxResponse::ajaxOk('Accounting entry added.', $this->tablePatches($request));
        }

        return redirect()->route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger'))))
            ->with('status', 'Accounting entry added.');
    }

    public function edit(Request $request, AccountingEntry $entry): View
    {
        $scope = $this->normalizeScope($request->query('scope', 'ledger'));
        $search = trim((string) $request->query('search', ''));

        if (AjaxResponse::ajaxFromRequest($request)) {
            return view('admin.accounting.partials.form', array_merge(
                $this->formData($entry->type, $entry->invoice, $entry),
                [
                    'formAction' => route('admin.accounting.update', $entry),
                    'formMethod' => 'PUT',
                    'scope' => $scope,
                    'search' => $search,
                ]
            ));
        }

        return view('admin.accounting.edit', $this->formData($entry->type, $entry->invoice, $entry));
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
            return AjaxResponse::ajaxOk('Accounting entry updated.', $this->tablePatches($request));
        }

        return redirect()->route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger'))))
            ->with('status', 'Accounting entry updated.');
    }

    public function destroy(Request $request, AccountingEntry $entry): RedirectResponse|JsonResponse
    {
        $entry->delete();

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Accounting entry deleted.', $this->tablePatches($request), closeModal: false);
        }

        return redirect()->route($this->scopeRoute($this->normalizeScope($request->input('scope', 'ledger'))))
            ->with('status', 'Accounting entry deleted.');
    }

    private function renderIndex(Request $request, string $scope, string $pageTitle, ?array $types)
    {
        $search = trim((string) $request->input('search', ''));
        $payload = [
            'entries' => $this->queryEntries($types, $search)->get(),
            'pageTitle' => $pageTitle,
            'scope' => $scope,
            'search' => $search,
        ];

        return view('admin.accounting.index', $payload);
    }

    private function queryEntries(?array $types, string $search)
    {
        $query = AccountingEntry::query()
            ->with(['customer', 'invoice', 'paymentGateway'])
            ->latest('entry_date')
            ->latest('id');

        if ($types) {
            $query->whereIn('type', $types);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('reference', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('customer', function ($customerQuery) use ($search) {
                        $customerQuery->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                        $invoiceQuery->where('number', 'like', '%' . $search . '%')
                            ->orWhere('id', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('paymentGateway', function ($gatewayQuery) use ($search) {
                        $gatewayQuery->where('name', 'like', '%' . $search . '%');
                    });

                if (is_numeric($search)) {
                    $inner->orWhere('id', (int) $search)
                        ->orWhere('amount', (float) $search);
                }
            });
        }

        return $query;
    }

    private function tablePatches(Request $request): array
    {
        $scope = $this->normalizeScope($request->input('scope', $request->query('scope', 'ledger')));
        $search = trim((string) $request->input('search', $request->query('search', '')));

        return [
            [
                'action' => 'replace',
                'selector' => '#accountingTableWrap',
                'html' => view('admin.accounting.partials.table', [
                    'entries' => $this->queryEntries($this->scopeTypes($scope), $search)->get(),
                    'scope' => $scope,
                    'search' => $search,
                ])->render(),
            ],
        ];
    }

    private function normalizeScope(string $scope): string
    {
        return in_array($scope, ['ledger', 'transactions', 'refunds', 'credits', 'expenses'], true)
            ? $scope
            : 'ledger';
    }

    private function scopeTypes(string $scope): ?array
    {
        return match ($scope) {
            'transactions' => ['payment', 'refund'],
            'refunds' => ['refund'],
            'credits' => ['credit'],
            'expenses' => ['expense'],
            default => null,
        };
    }

    private function scopeRoute(string $scope): string
    {
        return match ($scope) {
            'transactions' => 'admin.accounting.transactions',
            'refunds' => 'admin.accounting.refunds',
            'credits' => 'admin.accounting.credits',
            'expenses' => 'admin.accounting.expenses',
            default => 'admin.accounting.index',
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
}
