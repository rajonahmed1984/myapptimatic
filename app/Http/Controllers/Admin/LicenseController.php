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

class LicenseController extends Controller
{
    public function index()
    {
        return view('admin.licenses.index', [
            'licenses' => License::query()->with(['product', 'subscription.customer'])->latest()->get(),
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
            'max_domains' => ['required', 'integer', 'min:1', 'max:50'],
            'allowed_domains' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $licenseKey = $data['license_key'] ?: License::generateKey();

        $license = License::create([
            'subscription_id' => $data['subscription_id'],
            'product_id' => $data['product_id'],
            'license_key' => $licenseKey,
            'status' => $data['status'],
            'starts_at' => $data['starts_at'],
            'expires_at' => $data['expires_at'] ?? null,
            'max_domains' => $data['max_domains'],
            'notes' => $data['notes'] ?? null,
        ]);

        if (! empty($data['allowed_domains'])) {
            $domains = collect(preg_split('/\r\n|\r|\n/', $data['allowed_domains']))
                ->map(fn ($domain) => trim($domain))
                ->filter()
                ->unique();

            foreach ($domains as $domain) {
                LicenseDomain::create([
                    'license_id' => $license->id,
                    'domain' => $domain,
                    'status' => 'active',
                    'verified_at' => Carbon::now(),
                ]);
            }
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
            'max_domains' => ['required', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $license->update($data);

        return redirect()->route('admin.licenses.edit', $license)
            ->with('status', 'License updated.');
    }
}
