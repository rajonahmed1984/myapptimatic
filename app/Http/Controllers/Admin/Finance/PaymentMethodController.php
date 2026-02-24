<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Controller;
use App\Models\CommissionPayout;
use App\Models\EmployeePayout;
use App\Models\PaymentMethod;
use App\Models\PayrollAuditLog;
use App\Models\PayrollItem;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PaymentMethodController extends Controller
{
    public function index(
        Request $request,
    ): InertiaResponse {
        $editMethod = null;
        if ($request->filled('edit')) {
            $editMethod = PaymentMethod::query()->find($request->integer('edit'));
        }

        $methods = PaymentMethod::query()
            ->ordered()
            ->get();

        $amountByMethod = [];
        foreach ($methods as $method) {
            $entries = $this->ledgerEntriesForMethod($method);
            $amountByMethod[$method->id] = $this->formatCurrencyTotals(
                $entries
                    ->groupBy('currency')
                    ->map(fn (Collection $rows) => (float) $rows->sum('amount'))
                    ->all()
            );
        }

        return Inertia::render(
            'Admin/Finance/PaymentMethods/Index',
            $this->indexInertiaProps($methods, $editMethod, $amountByMethod, $request)
        );
    }

    public function show(Request $request, PaymentMethod $paymentMethod): View
    {
        $entries = $this->ledgerEntriesForMethod($paymentMethod)
            ->sortByDesc(fn (array $row) => (string) $row['date'])
            ->values();

        $summary = [
            'total_entries' => $entries->count(),
            'total_amount' => $this->formatCurrencyTotals(
                $entries
                    ->groupBy('currency')
                    ->map(fn (Collection $rows) => (float) $rows->sum('amount'))
                    ->all()
            ),
        ];

        $perPage = 30;
        $page = max(1, (int) $request->integer('page', 1));
        $rows = $entries->forPage($page, $perPage)->values();
        $ledger = new LengthAwarePaginator(
            $rows,
            $entries->count(),
            $perPage,
            $page,
            ['path' => route('admin.finance.payment-methods.show', $paymentMethod)]
        );

        return view('admin.finance.payment-methods.show', [
            'paymentMethod' => $paymentMethod,
            'ledger' => $ledger,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:60', 'alpha_dash', 'unique:payment_methods,code'],
            'account_details' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        PaymentMethod::create([
            'name' => trim((string) $data['name']),
            'code' => $this->resolveCode((string) ($data['code'] ?? ''), (string) $data['name']),
            'account_details' => $data['account_details'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()
            ->route('admin.finance.payment-methods.index')
            ->with('status', 'Payment method added.');
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:60', 'alpha_dash', Rule::unique('payment_methods', 'code')->ignore($paymentMethod->id)],
            'account_details' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $paymentMethod->update([
            'name' => trim((string) $data['name']),
            'code' => $this->resolveCode((string) ($data['code'] ?? ''), (string) $data['name']),
            'account_details' => $data['account_details'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('admin.finance.payment-methods.index')
            ->with('status', 'Payment method updated.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete();

        return redirect()
            ->route('admin.finance.payment-methods.index')
            ->with('status', 'Payment method deleted.');
    }

    private function resolveCode(string $code, string $name): string
    {
        $code = trim($code);
        if ($code !== '') {
            return Str::of($code)->lower()->replace(' ', '-')->value();
        }

        return Str::of($name)->lower()->replace(' ', '-')->value();
    }

    private function ledgerEntriesForMethod(PaymentMethod $method): Collection
    {
        $code = (string) $method->code;

        $employeePayouts = EmployeePayout::query()
            ->with('employee:id,name')
            ->where('payout_method', $code)
            ->get()
            ->map(function (EmployeePayout $row) {
                return [
                    'date' => optional($row->paid_at)->toDateString() ?? optional($row->created_at)->toDateString(),
                    'type' => 'Employee payout',
                    'party' => (string) ($row->employee?->name ?? 'N/A'),
                    'reference' => (string) ($row->reference ?? '--'),
                    'amount' => (float) ($row->amount ?? 0),
                    'currency' => (string) ($row->currency ?? 'BDT'),
                ];
            });

        $commissionPayouts = CommissionPayout::query()
            ->with('salesRep:id,name')
            ->where('payout_method', $code)
            ->where('status', 'paid')
            ->get()
            ->map(function (CommissionPayout $row) {
                return [
                    'date' => optional($row->paid_at)->toDateString() ?? optional($row->created_at)->toDateString(),
                    'type' => 'Commission payout',
                    'party' => (string) ($row->salesRep?->name ?? 'N/A'),
                    'reference' => (string) ($row->reference ?? '--'),
                    'amount' => (float) ($row->total_amount ?? 0),
                    'currency' => (string) ($row->currency ?? 'BDT'),
                ];
            });

        $payrollPayments = PayrollAuditLog::query()
            ->with('payrollItem.employee:id,name', 'payrollItem:id,employee_id,currency')
            ->whereIn('event', ['payment_partial', 'payment_completed'])
            ->get()
            ->filter(function (PayrollAuditLog $log) use ($method) {
                $reference = (string) data_get($log->meta, 'reference', '');

                return $this->matchesMethodReference($reference, $method);
            })
            ->map(function (PayrollAuditLog $log) {
                $paidAt = data_get($log->meta, 'paid_at');
                $date = $paidAt ? Carbon::parse((string) $paidAt)->toDateString() : optional($log->created_at)->toDateString();

                return [
                    'payroll_item_id' => (int) ($log->payroll_item_id ?? 0),
                    'date' => $date,
                    'type' => 'Payroll payment',
                    'party' => (string) ($log->payrollItem?->employee?->name ?? 'N/A'),
                    'reference' => (string) (data_get($log->meta, 'reference') ?? '--'),
                    'amount' => (float) (data_get($log->meta, 'amount') ?? 0),
                    'currency' => (string) ($log->payrollItem?->currency ?? 'BDT'),
                ];
            });

        // Fallback for legacy payroll payments where audit rows were not created.
        $loggedPayrollItemIds = $payrollPayments
            ->map(fn (array $row) => (int) ($row['payroll_item_id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->values();

        $legacyPayrollPayments = PayrollItem::query()
            ->with('employee:id,name')
            ->where(function ($query) use ($method) {
                $query->where('payment_reference', $method->name)
                    ->orWhere('payment_reference', 'like', $method->name.' - %')
                    ->orWhere('payment_reference', ucfirst((string) $method->code))
                    ->orWhere('payment_reference', 'like', ucfirst((string) $method->code).' - %');
            })
            ->where(function ($query) {
                $query->where('paid_amount', '>', 0)
                    ->orWhere('status', 'paid');
            })
            ->get()
            ->filter(function (PayrollItem $item) use ($loggedPayrollItemIds) {
                return ! $loggedPayrollItemIds->contains((int) $item->id);
            })
            ->map(function (PayrollItem $item) {
                $amount = (float) ($item->paid_amount ?? 0);
                if ($amount <= 0 && $item->status === 'paid') {
                    $amount = (float) ($item->net_pay ?? 0);
                }

                return [
                    'date' => optional($item->paid_at)->toDateString() ?? optional($item->updated_at)->toDateString(),
                    'type' => 'Payroll payment',
                    'party' => (string) ($item->employee?->name ?? 'N/A'),
                    'reference' => (string) ($item->payment_reference ?? '--'),
                    'amount' => max(0, $amount),
                    'currency' => (string) ($item->currency ?? 'BDT'),
                ];
            });

        return $employeePayouts
            ->concat($commissionPayouts)
            ->concat($payrollPayments)
            ->concat($legacyPayrollPayments)
            ->sortByDesc(fn (array $row) => (string) $row['date'])
            ->values();
    }

    private function matchesMethodReference(string $reference, PaymentMethod $method): bool
    {
        $reference = trim($reference);
        if ($reference === '') {
            return false;
        }

        $codeLabel = ucfirst((string) $method->code);
        $nameLabel = trim((string) $method->name);
        $candidates = array_filter([$codeLabel, $nameLabel]);

        foreach ($candidates as $candidate) {
            if (Str::lower($reference) === Str::lower($candidate)) {
                return true;
            }

            if (Str::startsWith(Str::lower($reference), Str::lower($candidate.' - '))) {
                return true;
            }
        }

        return false;
    }

    private function formatCurrencyTotals(array $totalsByCurrency): string
    {
        if (empty($totalsByCurrency)) {
            return '0.00';
        }

        $parts = [];
        foreach ($totalsByCurrency as $currency => $amount) {
            if ((float) $amount <= 0) {
                continue;
            }

            $parts[] = number_format((float) $amount, 2).' '.$currency;
        }

        if (empty($parts)) {
            return '0.00';
        }

        return implode(' + ', $parts);
    }

    private function indexInertiaProps(
        Collection $methods,
        ?PaymentMethod $editMethod,
        array $amountByMethod,
        Request $request
    ): array {
        $old = $request->session()->getOldInput();

        return [
            'pageTitle' => 'Payment Methods',
            'form' => [
                'title' => $editMethod ? 'Edit payment method' : 'Add payment method',
                'action' => $editMethod
                    ? route('admin.finance.payment-methods.update', $editMethod)
                    : route('admin.finance.payment-methods.store'),
                'method' => $editMethod ? 'PUT' : 'POST',
                'cancel_href' => $editMethod ? route('admin.finance.payment-methods.index') : null,
                'fields' => [
                    'name' => (string) ($old['name'] ?? $editMethod?->name ?? ''),
                    'code' => (string) ($old['code'] ?? $editMethod?->code ?? ''),
                    'sort_order' => (int) ($old['sort_order'] ?? $editMethod?->sort_order ?? 0),
                    'account_details' => (string) ($old['account_details'] ?? $editMethod?->account_details ?? ''),
                    'is_active' => array_key_exists('is_active', $old)
                        ? (bool) $old['is_active']
                        : (bool) ($editMethod?->is_active ?? true),
                ],
            ],
            'methods' => $methods->map(function (PaymentMethod $method) use ($amountByMethod) {
                return [
                    'id' => $method->id,
                    'name' => (string) $method->name,
                    'code' => (string) $method->code,
                    'amount_display' => (string) ($amountByMethod[$method->id] ?? '0.00'),
                    'account_details' => $method->account_details ? (string) $method->account_details : '--',
                    'is_active' => (bool) $method->is_active,
                    'sort_order' => (int) $method->sort_order,
                    'routes' => [
                        'show' => route('admin.finance.payment-methods.show', $method),
                        'edit' => route('admin.finance.payment-methods.index', ['edit' => $method->id]),
                        'destroy' => route('admin.finance.payment-methods.destroy', $method),
                    ],
                ];
            })->values()->all(),
        ];
    }
}
