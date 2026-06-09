<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingEntry;
use App\Models\CommissionPayout;
use App\Models\EmployeePayout;
use App\Models\ExpenseInvoicePayment;
use App\Models\PaymentGateway;
use App\Models\PaymentMethod;
use App\Models\PayrollAuditLog;
use App\Models\PayrollItem;
use App\Models\Setting;
use App\Support\Currency;
use App\Support\SystemLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PaymentGatewayController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $gateways = PaymentGateway::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $methods = $this->methodCatalog($gateways);

        $methodLedgerMap = [];
        foreach ($methods as $method) {
            $methodLedgerMap[$method->id] = $this->ledgerEntriesForMethod($method);
        }

        $accountingByGateway = collect();
        $gatewayIds = $gateways->pluck('id')->filter()->values();
        if ($gatewayIds->isNotEmpty()) {
            $accountingByGateway = AccountingEntry::query()
                ->whereIn('payment_gateway_id', $gatewayIds->all())
                ->get(['id', 'payment_gateway_id', 'type', 'amount', 'currency', 'entry_date'])
                ->groupBy('payment_gateway_id');
        }

        return Inertia::render(
            'Admin/PaymentGateways/Index',
            $this->indexInertiaProps($gateways, $methods, $methodLedgerMap, $accountingByGateway)
        );
    }

    public function show(PaymentGateway $paymentGateway): InertiaResponse
    {
        $dateFormat = (string) config('app.date_format', 'd-m-Y');
        $methods = $this->methodCatalog(collect([$paymentGateway]));
        $isGatewayActive = (bool) $paymentGateway->is_active;

        $matchedMethods = $isGatewayActive
            ? $this->matchedMethodsForGateway($paymentGateway, $methods)
            : collect();

        $methodLedgerMap = [];
        foreach ($matchedMethods as $method) {
            $methodLedgerMap[$method->id] = $this->ledgerEntriesForMethod($method);
        }

        $legacyEntries = $matchedMethods
            ->flatMap(fn (PaymentMethod $method) => $methodLedgerMap[$method->id] ?? collect())
            ->values();
        $legacyLastEntry = $legacyEntries->sortByDesc(fn (array $entry) => (string) ($entry['date'] ?? ''))->first();
        $legacyTotals = $this->totalsByCurrencyFromLedgerEntries($legacyEntries);

        $methodDetails = $matchedMethods->map(function (PaymentMethod $method) use ($methodLedgerMap, $dateFormat) {
            $entries = collect($methodLedgerMap[$method->id] ?? [])->values();
            $lastEntry = $entries->sortByDesc(fn (array $entry) => (string) ($entry['date'] ?? ''))->first();

            return [
                'id' => $method->id,
                'name' => (string) $method->name,
                'code' => (string) $method->code,
                'account_details' => (string) ($method->account_details ?? '--'),
                'is_active' => (bool) $method->is_active,
                'entries_count' => $entries->count(),
                'total_display' => $this->formatCurrencyTotalsFromEntries($entries),
                'last_date_display' => ! empty($lastEntry['date'])
                    ? Carbon::parse((string) $lastEntry['date'])->format($dateFormat)
                    : '--',
            ];
        })->values();

        $gatewayAccountingEntries = $isGatewayActive
            ? AccountingEntry::query()
                ->with(['customer:id,name', 'invoice:id,number'])
                ->where('payment_gateway_id', $paymentGateway->id)
                ->get([
                    'id',
                    'payment_gateway_id',
                    'type',
                    'amount',
                    'currency',
                    'entry_date',
                    'reference',
                    'description',
                    'customer_id',
                    'invoice_id',
                ])
            : collect();

        $accountingSummary = $this->accountingSummaryForGateway($gatewayAccountingEntries, $dateFormat);
        $gatewayInflowTotals = $this->totalsByCurrencyFromAccountingEntries($gatewayAccountingEntries, false);
        $gatewayOutflowTotals = $this->totalsByCurrencyFromAccountingEntries($gatewayAccountingEntries, true);
        $combinedOutflowTotals = $this->mergeCurrencyTotals($gatewayOutflowTotals, $legacyTotals);
        $latestActivityDate = $this->resolveLatestActivityDate(
            $gatewayAccountingEntries,
            (string) ($legacyLastEntry['date'] ?? '')
        );

        $accountingRows = $gatewayAccountingEntries
            ->map(function (AccountingEntry $entry) use ($dateFormat) {
                $isOutflow = $entry->isOutflow();
                $currency = strtoupper((string) $entry->currency);
                $amountDisplay = number_format((float) $entry->amount, 2).' '.$currency;

                return [
                    'id' => 'acc-'.$entry->id,
                    'date_iso' => $entry->entry_date?->toDateString() ?? '',
                    'date_display' => $entry->entry_date?->format($dateFormat) ?? '--',
                    'source' => 'Gateway',
                    'type_label' => ucfirst((string) $entry->type),
                    'party' => (string) ($entry->customer?->name ?? '--'),
                    'reference' => (string) ($entry->reference ?: ($entry->invoice?->number ?? '--')),
                    'description' => (string) ($entry->description ?: '--'),
                    'in_display' => $isOutflow ? '-' : $amountDisplay,
                    'out_display' => $isOutflow ? $amountDisplay : '-',
                    'sort_key' => sprintf('%s|2|%010d', $entry->entry_date?->toDateString() ?? '', (int) $entry->id),
                ];
            })
            ->values();

        $methodRows = $matchedMethods
            ->flatMap(function (PaymentMethod $method) use ($methodLedgerMap, $dateFormat) {
                $entries = collect($methodLedgerMap[$method->id] ?? [])->values();

                return $entries->map(function (array $entry, int $index) use ($method, $dateFormat) {
                    $date = (string) ($entry['date'] ?? '');
                    $parsedDate = trim($date) !== '' ? Carbon::parse($date) : null;
                    $currency = strtoupper((string) ($entry['currency'] ?? 'BDT'));
                    $amountDisplay = number_format((float) ($entry['amount'] ?? 0), 2).' '.$currency;

                    return [
                        'id' => 'method-'.$method->id.'-'.$index,
                        'date_iso' => $parsedDate?->toDateString() ?? '',
                        'date_display' => $parsedDate?->format($dateFormat) ?? '--',
                        'source' => 'Method',
                        'type_label' => (string) ($entry['type'] ?? 'Method payout'),
                        'party' => (string) ($entry['party'] ?? '--'),
                        'reference' => (string) ($entry['reference'] ?? '--'),
                        'description' => 'Matched method: '.$method->name,
                        'in_display' => '-',
                        'out_display' => $amountDisplay,
                        'sort_key' => sprintf('%s|1|%010d', $parsedDate?->toDateString() ?? '', $index),
                    ];
                });
            })
            ->values();

        $entries = $accountingRows
            ->concat($methodRows)
            ->sortByDesc(fn (array $entry) => (string) ($entry['sort_key'] ?? ''))
            ->map(function (array $entry) {
                unset($entry['sort_key']);

                return $entry;
            })
            ->values()
            ->all();

        return Inertia::render('Admin/PaymentGateways/Show', [
            'pageTitle' => (string) $paymentGateway->name.' Ledger',
            'gateway' => [
                'id' => $paymentGateway->id,
                'name' => (string) $paymentGateway->name,
                'driver' => ucfirst((string) $paymentGateway->driver),
                'slug' => (string) $paymentGateway->slug,
                'details_display' => $this->gatewayDetailsDisplay($paymentGateway),
                'is_active' => $isGatewayActive,
                'linked_methods' => $methodDetails->all(),
                'accounting_summary' => $accountingSummary,
                'legacy_summary' => [
                    'entries_count' => $legacyEntries->count(),
                    'total_display' => $this->formatCurrencyTotalsFromEntries($legacyEntries),
                    'last_date_display' => ! empty($legacyLastEntry['date'])
                        ? Carbon::parse((string) $legacyLastEntry['date'])->format($dateFormat)
                        : '--',
                ],
                'financial_summary' => [
                    'transactions_count' => $gatewayAccountingEntries->count() + $legacyEntries->count(),
                    'tk_in_display' => $this->formatCurrencyTotals($gatewayInflowTotals),
                    'tk_out_display' => $this->formatCurrencyTotals($combinedOutflowTotals),
                    'gateway_out_display' => $this->formatCurrencyTotals($gatewayOutflowTotals),
                    'method_out_display' => $this->formatCurrencyTotals($legacyTotals),
                    'last_activity_display' => $latestActivityDate?->format($dateFormat) ?? '--',
                ],
            ],
            'entries' => $entries,
            'routes' => [
                'index' => route('admin.payment-gateways.index'),
                'edit' => route('admin.payment-gateways.edit', $paymentGateway),
            ],
        ]);
    }

    public function edit(PaymentGateway $paymentGateway): InertiaResponse
    {
        $defaultCurrency = strtoupper((string) Setting::getValue('currency', Currency::DEFAULT));
        if (! Currency::isAllowed($defaultCurrency)) {
            $defaultCurrency = Currency::DEFAULT;
        }

        return Inertia::render(
            'Admin/PaymentGateways/Edit',
            $this->editInertiaProps($paymentGateway, $defaultCurrency)
        );
    }

    public function update(Request $request, PaymentGateway $paymentGateway): RedirectResponse
    {
        $wasActive = (bool) $paymentGateway->is_active;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'instructions' => ['nullable', 'string'],
            'payment_url' => ['nullable', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'routing_number' => ['nullable', 'string', 'max:255'],
            'merchant_number' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'merchant_short_code' => ['nullable', 'string', 'max:255'],
            'service_id' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'app_key' => ['nullable', 'string', 'max:255'],
            'app_secret' => ['nullable', 'string', 'max:255'],
            'button_label' => ['nullable', 'string', 'max:255'],
            'store_id' => ['nullable', 'string', 'max:255'],
            'store_password' => ['nullable', 'string', 'max:255'],
            'easy_checkout' => ['nullable', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'paypal_email' => ['nullable', 'email', 'max:255'],
            'api_username' => ['nullable', 'string', 'max:255'],
            'api_password' => ['nullable', 'string', 'max:255'],
            'api_signature' => ['nullable', 'string', 'max:255'],
            'force_one_time' => ['nullable', 'boolean'],
            'force_subscriptions' => ['nullable', 'boolean'],
            'require_shipping' => ['nullable', 'boolean'],
            'client_address_matching' => ['nullable', 'boolean'],
            'processing_currency' => ['nullable', 'string', 'size:3', Rule::in(Currency::allowed())],
            'sandbox' => ['nullable', 'boolean'],
            'deactivate' => ['nullable', 'boolean'],
        ]);

        $settings = $paymentGateway->settings ?? [];

        switch ($paymentGateway->driver) {
            case 'manual':
                $settings = array_merge($settings, [
                    'instructions' => $data['instructions'] ?? '',
                    'payment_url' => $data['payment_url'] ?? '',
                    'account_name' => $data['account_name'] ?? '',
                    'account_number' => $data['account_number'] ?? '',
                    'bank_name' => $data['bank_name'] ?? '',
                    'branch' => $data['branch'] ?? '',
                    'routing_number' => $data['routing_number'] ?? '',
                    'button_label' => $data['button_label'] ?? '',
                ]);
                break;
            case 'bkash':
                $settings = array_merge($settings, [
                    'merchant_number' => $data['merchant_number'] ?? '',
                    'instructions' => $data['instructions'] ?? '',
                    'payment_url' => $data['payment_url'] ?? '',
                    'account_name' => $data['account_name'] ?? '',
                    'account_number' => $data['account_number'] ?? '',
                    'button_label' => $data['button_label'] ?? '',
                ]);
                break;
            case 'bkash_api':
                $settings = array_merge($settings, [
                    'username' => $data['username'] ?? '',
                    'password' => $data['password'] ?? '',
                    'app_key' => $data['app_key'] ?? '',
                    'app_secret' => $data['app_secret'] ?? '',
                    'instructions' => $data['instructions'] ?? '',
                    'button_label' => $data['button_label'] ?? '',
                    'sandbox' => $request->boolean('sandbox'),
                ]);
                break;
            case 'sslcommerz':
                $settings = array_merge($settings, [
                    'instructions' => $data['instructions'] ?? '',
                    'payment_url' => $data['payment_url'] ?? '',
                    'store_id' => $data['store_id'] ?? '',
                    'store_password' => $data['store_password'] ?? '',
                    'button_label' => $data['button_label'] ?? '',
                    'easy_checkout' => $request->boolean('easy_checkout'),
                    'processing_currency' => isset($data['processing_currency'])
                        ? strtoupper($data['processing_currency'])
                        : '',
                    'sandbox' => $request->boolean('sandbox'),
                ]);
                break;
            case 'paypal':
                $settings = array_merge($settings, [
                    'instructions' => $data['instructions'] ?? '',
                    'payment_url' => $data['payment_url'] ?? '',
                    'paypal_email' => $data['paypal_email'] ?? '',
                    'api_username' => $data['api_username'] ?? '',
                    'api_password' => $data['api_password'] ?? '',
                    'api_signature' => $data['api_signature'] ?? '',
                    'force_one_time' => $request->boolean('force_one_time'),
                    'force_subscriptions' => $request->boolean('force_subscriptions'),
                    'require_shipping' => $request->boolean('require_shipping'),
                    'client_address_matching' => $request->boolean('client_address_matching'),
                    'client_id' => $data['client_id'] ?? '',
                    'client_secret' => $data['client_secret'] ?? '',
                    'button_label' => $data['button_label'] ?? '',
                    'processing_currency' => 'USD',
                    'sandbox' => $request->boolean('sandbox'),
                ]);
                break;
        }

        $isActive = $request->boolean('is_active');
        if ($request->boolean('deactivate')) {
            $isActive = false;
        }

        $paymentGateway->update([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? $paymentGateway->sort_order,
            'is_active' => $isActive,
            'settings' => $settings,
        ]);

        SystemLogger::write('activity', 'Payment gateway updated.', [
            'gateway_id' => $paymentGateway->id,
            'name' => $paymentGateway->name,
            'driver' => $paymentGateway->driver,
            'was_active' => $wasActive,
            'is_active' => $isActive,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.payment-gateways.index')
            ->with('status', 'Payment gateway updated.');
    }

    private function indexInertiaProps(
        Collection $gateways,
        Collection $methods,
        array $methodLedgerMap,
        Collection $accountingByGateway
    ): array
    {
        $dateFormat = (string) config('app.date_format', 'd-m-Y');

        return [
            'pageTitle' => 'Payment Gateways',
            'gateways' => collect($gateways)->map(function (PaymentGateway $gateway) use ($methods, $methodLedgerMap, $accountingByGateway, $dateFormat) {
                $isGatewayActive = (bool) $gateway->is_active;
                $matchedMethods = $isGatewayActive
                    ? $this->matchedMethodsForGateway($gateway, $methods)
                    : collect();
                $legacyEntries = $matchedMethods
                    ->flatMap(fn (PaymentMethod $method) => $methodLedgerMap[$method->id] ?? collect())
                    ->values();
                $legacyLastEntry = $legacyEntries->sortByDesc(fn (array $entry) => (string) ($entry['date'] ?? ''))->first();
                $legacyTotals = $this->totalsByCurrencyFromLedgerEntries($legacyEntries);

                $methodDetails = $matchedMethods->map(function (PaymentMethod $method) use ($methodLedgerMap, $dateFormat) {
                    $entries = collect($methodLedgerMap[$method->id] ?? [])->values();
                    $lastEntry = $entries->sortByDesc(fn (array $entry) => (string) ($entry['date'] ?? ''))->first();

                    return [
                        'id' => $method->id,
                        'name' => (string) $method->name,
                        'code' => (string) $method->code,
                        'account_details' => (string) ($method->account_details ?? '--'),
                        'is_active' => (bool) $method->is_active,
                        'entries_count' => $entries->count(),
                        'total_display' => $this->formatCurrencyTotalsFromEntries($entries),
                        'last_date_display' => ! empty($lastEntry['date'])
                            ? Carbon::parse((string) $lastEntry['date'])->format($dateFormat)
                            : '--',
                    ];
                })->values();

                $gatewayAccountingEntries = $isGatewayActive
                    ? collect($accountingByGateway->get($gateway->id, []))->values()
                    : collect();
                $accountingSummary = $this->accountingSummaryForGateway($gatewayAccountingEntries, $dateFormat);
                $gatewayInflowTotals = $this->totalsByCurrencyFromAccountingEntries($gatewayAccountingEntries, false);
                $gatewayOutflowTotals = $this->totalsByCurrencyFromAccountingEntries($gatewayAccountingEntries, true);
                $combinedOutflowTotals = $this->mergeCurrencyTotals($gatewayOutflowTotals, $legacyTotals);
                $latestActivityDate = $this->resolveLatestActivityDate(
                    $gatewayAccountingEntries,
                    (string) ($legacyLastEntry['date'] ?? '')
                );

                return [
                    'id' => $gateway->id,
                    'name' => $gateway->name,
                    'driver' => ucfirst((string) $gateway->driver),
                    'slug' => (string) $gateway->slug,
                    'details_display' => $this->gatewayDetailsDisplay($gateway),
                    'is_active' => (bool) $gateway->is_active,
                    'accounting_summary' => $accountingSummary,
                    'linked_methods' => $methodDetails->all(),
                    'legacy_summary' => [
                        'entries_count' => $legacyEntries->count(),
                        'total_display' => $this->formatCurrencyTotalsFromEntries($legacyEntries),
                        'last_date_display' => ! empty($legacyLastEntry['date'])
                            ? Carbon::parse((string) $legacyLastEntry['date'])->format($dateFormat)
                            : '--',
                    ],
                    'financial_summary' => [
                        'transactions_count' => $gatewayAccountingEntries->count() + $legacyEntries->count(),
                        'tk_in_display' => $this->formatCurrencyTotals($gatewayInflowTotals),
                        'tk_out_display' => $this->formatCurrencyTotals($combinedOutflowTotals),
                        'gateway_out_display' => $this->formatCurrencyTotals($gatewayOutflowTotals),
                        'method_out_display' => $this->formatCurrencyTotals($legacyTotals),
                        'last_activity_display' => $latestActivityDate?->format($dateFormat) ?? '--',
                    ],
                    'routes' => [
                        'view' => route('admin.payment-gateways.show', $gateway),
                        'edit' => route('admin.payment-gateways.edit', $gateway),
                    ],
                ];
            })->values()->all(),
        ];
    }

    private function accountingSummaryForGateway(Collection $entries, string $dateFormat): array
    {
        $inflowByCurrency = $this->totalsByCurrencyFromAccountingEntries($entries, false);
        $outflowByCurrency = $this->totalsByCurrencyFromAccountingEntries($entries, true);
        $latestEntry = $entries
            ->sortByDesc(fn (AccountingEntry $entry) => (string) ($entry->entry_date?->toDateString() ?? ''))
            ->first();

        return [
            'entries_count' => $entries->count(),
            'in_display' => $this->formatCurrencyTotals($inflowByCurrency),
            'out_display' => $this->formatCurrencyTotals($outflowByCurrency),
            'latest_entry_display' => $latestEntry?->entry_date?->format($dateFormat) ?? '--',
        ];
    }

    private function totalsByCurrencyFromAccountingEntries(Collection $entries, bool $outflow): array
    {
        return $entries
            ->filter(function (AccountingEntry $entry) use ($outflow) {
                return $outflow ? $entry->isOutflow() : ! $entry->isOutflow();
            })
            ->groupBy(fn (AccountingEntry $entry) => strtoupper((string) $entry->currency))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'))
            ->all();
    }

    private function totalsByCurrencyFromLedgerEntries(Collection $entries): array
    {
        return $entries
            ->groupBy(fn (array $entry) => strtoupper((string) ($entry['currency'] ?? 'BDT')))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'))
            ->all();
    }

    private function mergeCurrencyTotals(array ...$totalSets): array
    {
        $merged = [];

        foreach ($totalSets as $totalsByCurrency) {
            foreach ($totalsByCurrency as $currency => $amount) {
                $key = strtoupper((string) $currency);
                $merged[$key] = (float) ($merged[$key] ?? 0) + (float) $amount;
            }
        }

        return $merged;
    }

    private function resolveLatestActivityDate(Collection $accountingEntries, string $legacyDate): ?Carbon
    {
        $latestAccountingDate = $accountingEntries
            ->sortByDesc(fn (AccountingEntry $entry) => (string) ($entry->entry_date?->toDateString() ?? ''))
            ->first()?->entry_date;
        $latestLegacyDate = trim($legacyDate) !== '' ? Carbon::parse($legacyDate) : null;

        if ($latestAccountingDate === null) {
            return $latestLegacyDate;
        }

        if ($latestLegacyDate === null) {
            return Carbon::parse($latestAccountingDate);
        }

        return Carbon::parse($latestAccountingDate)->greaterThan($latestLegacyDate)
            ? Carbon::parse($latestAccountingDate)
            : $latestLegacyDate;
    }

    private function matchedMethodsForGateway(PaymentGateway $gateway, Collection $methods): Collection
    {
        $settings = is_array($gateway->settings) ? $gateway->settings : [];
        $gatewayName = $this->normalizeLookup((string) $gateway->name);
        $gatewaySlug = $this->normalizeLookup((string) $gateway->slug);
        $gatewayDriver = $this->normalizeLookup((string) $gateway->driver);
        $gatewayAccountNumber = $this->normalizeLookup((string) ($settings['account_number'] ?? ''));
        $gatewayMerchantNumber = $this->normalizeLookup((string) ($settings['merchant_number'] ?? ''));
        $isCashGateway = $this->isCashGateway($gatewayName, $gatewaySlug);

        $strictMatches = $methods->filter(function (PaymentMethod $method) use ($gatewayName, $gatewaySlug, $gatewayDriver, $gatewayAccountNumber, $gatewayMerchantNumber, $isCashGateway) {
            $code = $this->normalizeLookup((string) $method->code);
            $name = $this->normalizeLookup((string) $method->name);
            $accountDetails = $this->normalizeLookup((string) ($method->account_details ?? ''));

            if ($isCashGateway) {
                return $code === 'cash'
                    || $name === 'cash'
                    || str_contains($code, 'cash')
                    || str_contains($name, 'cash');
            }

            if ($code !== '' && ($code === $gatewaySlug || $code === $gatewayDriver)) {
                return true;
            }

            if ($name !== '' && ($name === $gatewayName || $gatewayName === $code)) {
                return true;
            }

            if (
                $name !== ''
                && strlen($name) >= 6
                && (str_contains($gatewayName, $name) || str_contains($name, $gatewayName))
            ) {
                return true;
            }

            if (
                $gatewayAccountNumber !== ''
                && $accountDetails !== ''
                && (str_contains($accountDetails, $gatewayAccountNumber) || str_contains($gatewayAccountNumber, $accountDetails))
            ) {
                return true;
            }

            if (
                $gatewayMerchantNumber !== ''
                && (
                    ($code !== '' && (str_contains($code, $gatewayMerchantNumber) || str_contains($gatewayMerchantNumber, $code)))
                    || ($accountDetails !== '' && (str_contains($accountDetails, $gatewayMerchantNumber) || str_contains($gatewayMerchantNumber, $accountDetails)))
                )
            ) {
                return true;
            }

            return false;
        })->values();

        if ($strictMatches->isNotEmpty()) {
            return $strictMatches;
        }

        $keywordMatches = $methods->filter(function (PaymentMethod $method) use ($gatewayName, $gatewaySlug, $gatewayDriver, $isCashGateway) {
            $haystack = $this->normalizeLookup((string) $method->name.' '.(string) $method->code);

            if ($isCashGateway) {
                return str_contains($haystack, 'cash');
            }

            if (str_contains($gatewayDriver, 'bkash') || str_contains($gatewaySlug, 'bkash') || str_contains($gatewayName, 'bkash')) {
                return str_contains($haystack, 'bkash') || str_contains($haystack, 'mobile');
            }

            if (str_contains($gatewayDriver, 'paypal') || str_contains($gatewaySlug, 'paypal') || str_contains($gatewayName, 'paypal')) {
                return str_contains($haystack, 'paypal');
            }

            if (str_contains($gatewayName, 'bank') || str_contains($gatewaySlug, 'bank')) {
                return str_contains($haystack, 'bank');
            }

            return false;
        })->values();

        return $keywordMatches;
    }

    private function methodCatalog(Collection $gateways): Collection
    {
        $methods = collect();
        $nextId = 1;

        foreach (PaymentMethod::catalog() as $method) {
            $nextId = $this->appendMethodCandidate(
                $methods,
                $nextId,
                (string) $method->code,
                (string) $method->name,
                (string) ($method->account_details ?? ''),
                (bool) ($method->is_active ?? true)
            );
        }

        foreach ($gateways as $gateway) {
            if (! $gateway instanceof PaymentGateway) {
                continue;
            }

            $settings = is_array($gateway->settings) ? $gateway->settings : [];
            $name = (string) ($gateway->name ?? '');
            $slug = (string) ($gateway->slug ?? '');
            $driver = (string) ($gateway->driver ?? '');
            $accountNumber = trim((string) ($settings['account_number'] ?? ''));
            $merchantNumber = trim((string) ($settings['merchant_number'] ?? ''));
            $isActive = (bool) ($gateway->is_active ?? true);

            $nextId = $this->appendMethodCandidate(
                $methods,
                $nextId,
                $slug !== '' ? $slug : (string) $gateway->id,
                $name !== '' ? $name : $this->humanizeGatewayToken($driver),
                $accountNumber,
                $isActive
            );

            if ($accountNumber !== '') {
                $nextId = $this->appendMethodCandidate(
                    $methods,
                    $nextId,
                    $accountNumber,
                    $name !== '' ? $name.' Account' : 'Gateway Account',
                    $accountNumber,
                    $isActive
                );
            }

            if ($merchantNumber !== '') {
                $nextId = $this->appendMethodCandidate(
                    $methods,
                    $nextId,
                    $merchantNumber,
                    $name !== '' ? $name.' Merchant' : 'Gateway Merchant',
                    $merchantNumber,
                    $isActive
                );
            }
        }

        return $methods->values();
    }

    private function appendMethodCandidate(
        Collection $methods,
        int $nextId,
        string $code,
        string $name,
        string $accountDetails,
        bool $isActive
    ): int {
        $code = trim($code);
        if ($code === '') {
            return $nextId;
        }

        $key = $this->normalizeLookup($code);
        if ($key === '') {
            return $nextId;
        }

        if ($methods->has($key)) {
            return $nextId;
        }

        $method = new PaymentMethod();
        $method->forceFill([
            'id' => $nextId,
            'code' => $code,
            'name' => trim($name) !== '' ? trim($name) : $code,
            'account_details' => trim($accountDetails),
            'is_active' => $isActive,
            'sort_order' => $nextId * 10,
        ]);

        $methods->put($key, $method);

        return $nextId + 1;
    }

    private function isCashGateway(string $gatewayName, string $gatewaySlug): bool
    {
        return str_contains($gatewaySlug, 'cash') || str_contains($gatewayName, 'cash');
    }

    private function gatewayDetailsDisplay(PaymentGateway $gateway): string
    {
        $settings = is_array($gateway->settings) ? $gateway->settings : [];
        $detailValues = collect([
            (string) ($settings['account_name'] ?? ''),
            (string) ($settings['account_number'] ?? ''),
            (string) ($settings['merchant_number'] ?? ''),
            (string) ($settings['merchant_short_code'] ?? ''),
            (string) ($settings['store_id'] ?? ''),
            (string) ($settings['paypal_email'] ?? ''),
            (string) ($settings['bank_name'] ?? ''),
        ])
            ->map(fn (string $value) => trim($value))
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values();

        $driverRaw = (string) $gateway->driver;
        $driverLabel = $this->normalizeLookup($driverRaw) === 'bkash'
            ? 'Manual bKash'
            : $this->humanizeGatewayToken($driverRaw);
        if ($detailValues->isNotEmpty()) {
            return $driverLabel.' | '.$detailValues->take(2)->implode(' | ');
        }

        $slugLabel = $this->humanizeGatewayToken((string) $gateway->slug);
        if ($this->normalizeLookup($driverLabel) === $this->normalizeLookup($slugLabel)) {
            return $driverLabel;
        }

        return $driverLabel.' / '.$slugLabel;
    }

    private function humanizeGatewayToken(string $value): string
    {
        $normalized = str_replace(['_', '-'], ' ', trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if ($normalized === '') {
            return '--';
        }

        return ucwords(strtolower($normalized));
    }

    private function normalizeLookup(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/', '', $value) ?? '');
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

        $expenseInvoicePayments = ExpenseInvoicePayment::query()
            ->with('invoice:id,invoice_no,currency')
            ->where('payment_method', $code)
            ->get()
            ->map(function (ExpenseInvoicePayment $row) {
                return [
                    'date' => optional($row->paid_at)->toDateString() ?? optional($row->created_at)->toDateString(),
                    'type' => 'Expense payment',
                    'party' => (string) ($row->invoice?->invoice_no ?? 'Expense invoice'),
                    'reference' => (string) ($row->payment_reference ?? '--'),
                    'amount' => (float) ($row->amount ?? 0),
                    'currency' => (string) ($row->invoice?->currency ?? 'BDT'),
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

        $loggedPayrollItemIds = $payrollPayments
            ->map(fn (array $row) => (int) ($row['payroll_item_id'] ?? 0))
            ->filter(fn (int $id) => $id > 0)
            ->values();

        $legacyPayrollPayments = PayrollItem::query()
            ->with('employee:id,name')
            ->where(function ($query) use ($method) {
                $query->where('payment_reference', $method->name)
                    ->orWhere('payment_reference', 'like', $method->name.' - %')
                    ->orWhere('payment_reference', (string) $method->code)
                    ->orWhere('payment_reference', 'like', (string) $method->code.' - %')
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
            ->concat($expenseInvoicePayments)
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

        $codeRaw = trim((string) $method->code);
        $codeLabel = ucfirst((string) $method->code);
        $nameLabel = trim((string) $method->name);
        $candidates = array_values(array_filter([$codeRaw, $codeLabel, $nameLabel]));

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

    private function formatCurrencyTotalsFromEntries(Collection $entries): string
    {
        $totalsByCurrency = $entries
            ->groupBy(fn (array $entry) => strtoupper((string) ($entry['currency'] ?? 'BDT')))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'))
            ->all();

        return $this->formatCurrencyTotals($totalsByCurrency);
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

    private function editInertiaProps(PaymentGateway $gateway, string $defaultCurrency): array
    {
        $settings = is_array($gateway->settings) ? $gateway->settings : [];

        return [
            'pageTitle' => 'Edit Payment Gateway',
            'gateway' => [
                'id' => $gateway->id,
                'driver' => (string) $gateway->driver,
                'name' => (string) old('name', (string) $gateway->name),
                'sort_order' => (int) old('sort_order', (int) $gateway->sort_order),
                'is_active' => (bool) old('is_active', (bool) $gateway->is_active),
                'deactivate' => (bool) old('deactivate', false),
                'fields' => [
                    'instructions' => (string) old('instructions', (string) ($settings['instructions'] ?? '')),
                    'payment_url' => (string) old('payment_url', (string) ($settings['payment_url'] ?? '')),
                    'account_name' => (string) old('account_name', (string) ($settings['account_name'] ?? '')),
                    'account_number' => (string) old('account_number', (string) ($settings['account_number'] ?? '')),
                    'bank_name' => (string) old('bank_name', (string) ($settings['bank_name'] ?? '')),
                    'branch' => (string) old('branch', (string) ($settings['branch'] ?? '')),
                    'routing_number' => (string) old('routing_number', (string) ($settings['routing_number'] ?? '')),
                    'merchant_number' => (string) old('merchant_number', (string) ($settings['merchant_number'] ?? '')),
                    'api_key' => (string) old('api_key', (string) ($settings['api_key'] ?? '')),
                    'merchant_short_code' => (string) old('merchant_short_code', (string) ($settings['merchant_short_code'] ?? '')),
                    'service_id' => (string) old('service_id', (string) ($settings['service_id'] ?? '')),
                    'username' => (string) old('username', (string) ($settings['username'] ?? '')),
                    'password' => (string) old('password', (string) ($settings['password'] ?? '')),
                    'app_key' => (string) old('app_key', (string) ($settings['app_key'] ?? '')),
                    'app_secret' => (string) old('app_secret', (string) ($settings['app_secret'] ?? '')),
                    'button_label' => (string) old('button_label', (string) ($settings['button_label'] ?? '')),
                    'store_id' => (string) old('store_id', (string) ($settings['store_id'] ?? '')),
                    'store_password' => (string) old('store_password', (string) ($settings['store_password'] ?? '')),
                    'client_id' => (string) old('client_id', (string) ($settings['client_id'] ?? '')),
                    'client_secret' => (string) old('client_secret', (string) ($settings['client_secret'] ?? '')),
                    'paypal_email' => (string) old('paypal_email', (string) ($settings['paypal_email'] ?? '')),
                    'api_username' => (string) old('api_username', (string) ($settings['api_username'] ?? '')),
                    'api_password' => (string) old('api_password', (string) ($settings['api_password'] ?? '')),
                    'api_signature' => (string) old('api_signature', (string) ($settings['api_signature'] ?? '')),
                    'processing_currency' => (string) old('processing_currency', (string) ($settings['processing_currency'] ?? $defaultCurrency)),
                    'sandbox' => (bool) old('sandbox', (bool) ($settings['sandbox'] ?? false)),
                    'easy_checkout' => (bool) old('easy_checkout', (bool) ($settings['easy_checkout'] ?? false)),
                    'force_one_time' => (bool) old('force_one_time', (bool) ($settings['force_one_time'] ?? false)),
                    'force_subscriptions' => (bool) old('force_subscriptions', (bool) ($settings['force_subscriptions'] ?? false)),
                    'require_shipping' => (bool) old('require_shipping', (bool) ($settings['require_shipping'] ?? false)),
                    'client_address_matching' => (bool) old('client_address_matching', (bool) ($settings['client_address_matching'] ?? false)),
                ],
            ],
            'currency_options' => array_values(Currency::allowed()),
            'default_currency' => $defaultCurrency,
            'routes' => [
                'index' => route('admin.payment-gateways.index'),
                'update' => route('admin.payment-gateways.update', $gateway),
            ],
        ];
    }
}
