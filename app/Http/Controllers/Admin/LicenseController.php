<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncLicenseJob;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\AnomalyFlag;
use App\Services\AccessBlockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\Setting;

class LicenseController extends Controller
{
    public function index()
    {
        $licenses = License::query()
            ->with(['product', 'subscription.customer', 'subscription.plan', 'subscription.latestOrder', 'domains'])
            ->latest()
            ->paginate(25);

        $accessBlockService = app(AccessBlockService::class);
        $accessBlockedCustomers = [];

        foreach ($licenses as $license) {
            $customer = $license->subscription?->customer;
            $customerId = $customer?->id;

            if (! $customerId || array_key_exists($customerId, $accessBlockedCustomers)) {
                continue;
            }

            $accessBlockedCustomers[$customerId] = $accessBlockService->isCustomerBlocked($customer);
        }

        $anomalyCounts = AnomalyFlag::query()
            ->selectRaw('model_id, COUNT(*) as total')
            ->where('model_type', License::class)
            ->where('state', 'open')
            ->groupBy('model_id')
            ->pluck('total', 'model_id');

        return view('admin.licenses.index', [
            'licenses' => $licenses,
            'accessBlockedCustomers' => $accessBlockedCustomers,
            'anomalyCounts' => $anomalyCounts,
        ]);
    }

    public function create()
    {
        return view('admin.licenses.create', [
            'products' => Product::query()->orderBy('name')->get(),
            'subscriptions' => Subscription::query()->with('customer')->orderBy('id', 'desc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'exists:subscriptions,id'],
            'product_id' => ['required', 'exists:products,id'],
            'license_key' => ['nullable', 'string', 'max:255', 'unique:licenses,license_key'],
            'status' => ['required', Rule::in(['active', 'suspended', 'revoked'])],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'allowed_domains' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $licenseKey = $data['license_key'] ?: License::generateKey();
        $allowedDomain = $this->extractSingleDomain($data['allowed_domains'] ?? null);

        if ($allowedDomain === false) {
            return back()
                ->withErrors(['allowed_domains' => 'Only one domain is allowed per license.'])
                ->withInput();
        }

        if ($allowedDomain && ! $this->normalizeDomain($allowedDomain)) {
            return back()
                ->withErrors(['allowed_domains' => 'Invalid domain format. Use only the hostname.'])
                ->withInput();
        }

        $license = License::create([
            'subscription_id' => $data['subscription_id'],
            'product_id' => $data['product_id'],
            'license_key' => $licenseKey,
            'status' => $data['status'],
            'starts_at' => $data['starts_at'],
            'expires_at' => $data['expires_at'] ?? null,
            'max_domains' => 1,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($allowedDomain) {
            LicenseDomain::create([
                'license_id' => $license->id,
                'domain' => $this->normalizeDomain($allowedDomain),
                'status' => 'active',
                'verified_at' => Carbon::now(),
            ]);
        }

        return redirect()->route('admin.licenses.edit', $license)
            ->with('status', 'License created.');
    }

    public function edit(License $license)
    {
        return view('admin.licenses.edit', [
            'license' => $license->load(['product', 'subscription.customer', 'subscription.plan.product', 'domains']),
            'products' => Product::query()->orderBy('name')->get(),
            'subscriptions' => Subscription::query()->with(['customer', 'plan.product'])->orderBy('id', 'desc')->get(),
        ]);
    }

    public function update(Request $request, License $license)
    {
        $data = $request->validate([
            'subscription_id' => ['required', 'exists:subscriptions,id'],
            'product_id' => ['required', 'exists:products,id'],
            'license_key' => ['required', 'string', 'max:255', Rule::unique('licenses', 'license_key')->ignore($license->id)],
            'status' => ['required', Rule::in(['active', 'suspended', 'revoked'])],
            'starts_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'domain_url' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['max_domains'] = 1;

        $license->update($data);

        $domainInput = trim((string) ($data['domain_url'] ?? ''));

        if ($domainInput !== '') {
            $domain = $this->normalizeDomain($domainInput);

            if (! $domain) {
                return back()
                    ->withErrors(['domain_url' => 'Invalid domain format. Use only the hostname or full URL.'])
                    ->withInput();
            }

            LicenseDomain::updateOrCreate(
                [
                    'license_id' => $license->id,
                    'domain' => $domain,
                ],
                [
                    'status' => 'active',
                    'verified_at' => Carbon::now(),
                ]
            );

            $license->domains()
                ->where('domain', '!=', $domain)
                ->update(['status' => 'revoked']);
        }

        return redirect()->route('admin.licenses.edit', $license)
            ->with('status', 'License updated.');
    }

    public function revokeDomain(License $license, LicenseDomain $domain)
    {
        if ($domain->license_id !== $license->id) {
            abort(404);
        }

        $domain->update([
            'status' => 'revoked',
        ]);

        return redirect()->route('admin.licenses.edit', $license)
            ->with('status', 'Domain revoked.');
    }

    public function destroy(License $license)
    {
        $license->delete();

        return redirect()->route('admin.licenses.index')
            ->with('status', 'License deleted.');
    }

    public function sync(License $license)
    {
        $this->authorize('update', $license);

        SyncLicenseJob::dispatch($license->id, request()->ip());

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'License sync queued.',
            ]);
        }

        return redirect()->route('admin.licenses.index')
            ->with('status', 'License sync queued.');
    }

    public function syncStatus(License $license)
    {
        $this->authorize('view', $license);

        $syncAt = $license->last_check_at;
        $syncLabel = 'Never';
        $syncClass = 'bg-slate-100 text-slate-600';

        if ($syncAt) {
            $hours = $syncAt->diffInHours(now());
            if ($hours <= 24) {
                $syncLabel = 'Synced';
                $syncClass = 'bg-emerald-100 text-emerald-700';
            } elseif ($hours > 48) {
                $syncLabel = 'Stale';
                $syncClass = 'bg-amber-100 text-amber-700';
            } else {
                $syncLabel = 'Synced';
                $syncClass = 'bg-emerald-100 text-emerald-700';
            }
        }

        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $displayAt = $syncAt ? $syncAt->format($dateFormat.' H:i') : 'No sync yet';

        return response()->json([
            'ok' => true,
            'data' => [
                'last_check_at' => $syncAt?->toDateTimeString(),
                'last_check_ip' => $license->last_check_ip,
                'sync_label' => $syncLabel,
                'sync_class' => $syncClass,
                'display_time' => $displayAt,
            ],
        ]);
    }

    private function extractSingleDomain(?string $input): string|bool|null
    {
        if ($input === null) {
            return null;
        }

        $domains = collect(preg_split('/\r\n|\r|\n/', $input))
            ->map(fn ($domain) => trim($domain))
            ->filter()
            ->unique()
            ->values();

        if ($domains->count() > 1) {
            return false;
        }

        return $domains->first();
    }

    private function normalizeDomain(string $input): ?string
    {
        $value = trim(strtolower($input));

        if (Str::startsWith($value, ['http://', 'https://'])) {
            $value = parse_url($value, PHP_URL_HOST) ?: '';
        }

        $value = preg_replace('/^www\./', '', $value);

        if ($value === '' || ! preg_match('/^[a-z0-9.-]+$/', $value)) {
            return null;
        }

        return $value;
    }
}
