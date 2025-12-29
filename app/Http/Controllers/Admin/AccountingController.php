<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentGateway;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountingController extends Controller
{
    private const TYPES = ['payment', 'refund', 'credit', 'expense'];

    public function index()
    {
        return $this->renderIndex('ledger', 'Ledger', null);
    }

    public function transactions()
    {
        return $this->renderIndex('transactions', 'Transactions', ['payment', 'refund']);
    }

    public function refunds()
    {
        return $this->renderIndex('refunds', 'Refunds', ['refund']);
    }

    public function credits()
    {
        return $this->renderIndex('credits', 'Credits', ['credit']);
    }

    public function expenses()
    {
        return $this->renderIndex('expenses', 'Expenses', ['expense']);
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

    private function renderIndex(string $scope, string $pageTitle, ?array $types)
    {
        $query = AccountingEntry::query()
            ->with(['customer', 'invoice', 'paymentGateway'])
            ->latest('entry_date')
            ->latest('id');

        if ($types) {
            $query->whereIn('type', $types);
        }

        return view('admin.accounting.index', [
            'entries' => $query->get(),
            'pageTitle' => $pageTitle,
            'scope' => $scope,
        ]);
    }

    private function formData(string $type, ?Invoice $selectedInvoice = null, ?AccountingEntry $entry = null): array
    {
        return [
            'entry' => $entry,
            'type' => $type,
            'selectedInvoice' => $selectedInvoice,
            'customers' => Customer::query()->orderBy('name')->get(),
            'invoices' => Invoice::query()->with('customer')->orderByDesc('issue_date')->get(),
            'gateways' => PaymentGateway::query()->orderBy('sort_order')->get(),
            'currency' => strtoupper((string) Setting::getValue('currency', 'USD')),
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
            'currency' => ['required', 'string', 'size:3'],
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
