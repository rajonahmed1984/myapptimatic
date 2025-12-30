<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Product;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class LicenseController extends Controller
{
    public function index()
    {
        return view('admin.licenses.index', [
            'licenses' => License::query()
                ->with(['product', 'subscription.customer', 'subscription.plan', 'subscription.latestOrder'])
                ->latest()
                ->get(),
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
            'license' => $license->load(['product', 'subscription.customer', 'domains']),
            'products' => Product::query()->orderBy('name')->get(),
            'subscriptions' => Subscription::query()->with('customer')->orderBy('id', 'desc')->get(),
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
            'notes' => ['nullable', 'string'],
        ]);

        $data['max_domains'] = 1;

        $license->update($data);

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
