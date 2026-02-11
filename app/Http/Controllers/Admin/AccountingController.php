<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Support\Currency;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function create(Request $request)
    {
        $type = $this->normalizeType($request->query('type', 'payment'));
        $selectedInvoice = $request->query('invoice_id')
            ? Invoice::query()->with('customer')->find($request->query('invoice_id'))
            : null;

        return view('admin.accounting.create', $this->formData($type, $selectedInvoice));
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $invoice] = $this->validateEntry($request);

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

        return redirect()->route('admin.accounting.index')
            ->with('status', 'Accounting entry added.');
    }

    public function edit(AccountingEntry $entry)
    {
        return view('admin.accounting.edit', $this->formData($entry->type, $entry->invoice, $entry));
    }

    public function update(Request $request, AccountingEntry $entry): RedirectResponse
    {
        [$data, $invoice] = $this->validateEntry($request);

        $entry->update($data);

        if ($entry->type === 'payment' && $invoice) {
            if ((float) $entry->amount >= (float) $invoice->total) {
                $invoice->update([
                    'status' => 'paid',
                    'paid_at' => $invoice->paid_at ?? Carbon::now(),
                ]);
            }
        }

        return redirect()->route('admin.accounting.index')
            ->with('status', 'Accounting entry updated.');
    }

    public function destroy(AccountingEntry $entry): RedirectResponse
    {
        $entry->delete();

        return redirect()->route('admin.accounting.index')
            ->with('status', 'Accounting entry deleted.');
    }

    private function renderIndex(Request $request, string $scope, string $pageTitle, ?array $types)
    {
        $search = trim((string) $request->input('search', ''));
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

        $payload = [
            'entries' => $query->get(),
            'pageTitle' => $pageTitle,
            'scope' => $scope,
            'search' => $search,
        ];

        if ($request->header('HX-Request')) {
            return view('admin.accounting.partials.table', $payload);
        }

        return view('admin.accounting.index', $payload);
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
