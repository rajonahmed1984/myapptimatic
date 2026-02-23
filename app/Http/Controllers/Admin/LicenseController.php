<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncLicenseJob;
use App\Models\AnomalyFlag;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\AccessBlockService;
use App\Support\HybridUiResponder;
use App\Support\UiFeature;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class LicenseController extends Controller
{
    public function index(
        Request $request,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $search = trim((string) $request->input('search', ''));

        $licenses = License::query()
            ->with(['product', 'subscription.customer', 'subscription.plan', 'subscription.latestOrder', 'domains'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('license_key', 'like', '%'.$search.'%')
                        ->orWhere('status', 'like', '%'.$search.'%')
                        ->orWhereHas('domains', function ($domainQuery) use ($search) {
                            $domainQuery->where('domain', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('product', function ($productQuery) use ($search) {
                            $productQuery->where('name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('subscription', function ($subscriptionQuery) use ($search) {
                            $subscriptionQuery->whereHas('plan', function ($planQuery) use ($search) {
                                $planQuery->where('name', 'like', '%'.$search.'%')
                                    ->orWhereHas('product', function ($planProductQuery) use ($search) {
                                        $planProductQuery->where('name', 'like', '%'.$search.'%');
                                    });
                            })
                                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                                    $customerQuery->where('name', 'like', '%'.$search.'%');
                                });
                        });

                    if (is_numeric($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

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

        $payload = [
            'licenses' => $licenses,
            'accessBlockedCustomers' => $accessBlockedCustomers,
            'anomalyCounts' => $anomalyCounts,
            'search' => $search,
        ];

        if ($request->header('HX-Request')) {
            return view('admin.licenses.partials.table', $payload);
        }

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_LICENSES_INDEX,
            'admin.licenses.index',
            $payload,
            'Admin/Licenses/Index',
            $this->indexInertiaProps(
                $licenses,
                $accessBlockedCustomers,
                $search,
                $request
            )
        );
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
            'allowed_domains' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['max_domains'] = 1;

        $license->update($data);

        $domainInput = $this->extractSingleDomain($data['allowed_domains'] ?? null);

        if ($domainInput === false) {
            return back()
                ->withErrors(['allowed_domains' => 'Only one domain is allowed per license.'])
                ->withInput();
        }

        if ($domainInput) {
            $domain = $this->normalizeDomain($domainInput);

            if (! $domain) {
                return back()
                    ->withErrors(['allowed_domains' => 'Invalid domain format. Use only the hostname or full URL.'])
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
        } else {
            $license->domains()->update(['status' => 'revoked']);
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

    private function indexInertiaProps(
        LengthAwarePaginator $licenses,
        array $accessBlockedCustomers,
        string $search,
        Request $request
    ): array {
        $dateFormat = (string) Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));

        return [
            'pageTitle' => 'Licenses',
            'search' => $search,
            'routes' => [
                'index' => route('admin.licenses.index'),
                'create' => route('admin.licenses.create'),
            ],
            'licenses' => collect($licenses->items())->values()->map(function (License $license) use ($accessBlockedCustomers, $dateFormat, $request) {
                $activeDomain = $license->domains->firstWhere('status', 'active');
                $domain = $activeDomain?->domain ?? $license->domains->first()?->domain;
                $subscription = $license->subscription;
                $customer = $subscription?->customer;
                $isBlocked = $customer && ($accessBlockedCustomers[$customer->id] ?? false);

                $verificationLabel = 'Verified';
                $verificationClass = 'bg-emerald-100 text-emerald-700';
                $verificationHint = 'Active and domain matched';

                if (! $customer || $customer->status !== 'active') {
                    $verificationLabel = 'Blocked';
                    $verificationClass = 'bg-rose-100 text-rose-700';
                    $verificationHint = 'customer_inactive';
                } elseif ($license->status !== 'active') {
                    $verificationLabel = 'Blocked';
                    $verificationClass = 'bg-rose-100 text-rose-700';
                    $verificationHint = 'license_inactive';
                } elseif ($license->expires_at && $license->expires_at->isPast()) {
                    $verificationLabel = 'Blocked';
                    $verificationClass = 'bg-rose-100 text-rose-700';
                    $verificationHint = 'license_expired';
                } elseif (! $subscription || $subscription->status !== 'active') {
                    $verificationLabel = 'Blocked';
                    $verificationClass = 'bg-rose-100 text-rose-700';
                    $verificationHint = 'subscription_inactive';
                } elseif (! $activeDomain) {
                    $verificationLabel = 'Pending';
                    $verificationClass = 'bg-amber-100 text-amber-700';
                    $verificationHint = 'domain_not_bound';
                } elseif ($isBlocked) {
                    $verificationLabel = 'Blocked';
                    $verificationClass = 'bg-rose-100 text-rose-700';
                    $verificationHint = 'invoice_overdue';
                }

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

                $latestOrder = $subscription?->latestOrder;
                $orderNumber = $latestOrder?->order_number ?? $latestOrder?->id;

                return [
                    'id' => $license->id,
                    'customer_name' => (string) ($customer?->name ?? '--'),
                    'customer_url' => $customer ? route('admin.customers.show', $customer) : null,
                    'is_blocked' => (bool) $isBlocked,
                    'order_number' => $orderNumber ? (string) $orderNumber : '--',
                    'product_name' => (string) ($license->product?->name ?? '--'),
                    'plan_name' => (string) ($subscription?->plan?->name ?? '--'),
                    'license_key' => (string) $license->license_key,
                    'domain' => (string) ($domain ?? '--'),
                    'verification_label' => $verificationLabel,
                    'verification_class' => $verificationClass,
                    'verification_hint' => $verificationHint,
                    'license_status' => (string) $license->status,
                    'sync_label' => $syncLabel,
                    'sync_class' => $syncClass,
                    'sync_time_display' => $syncAt ? $syncAt->format($dateFormat.' H:i') : 'No sync yet',
                    'can_sync' => (bool) ($request->user()?->can('update', $license)),
                    'routes' => [
                        'edit' => route('admin.licenses.edit', $license),
                        'sync' => route('admin.licenses.sync', $license),
                        'sync_status' => route('admin.licenses.sync-status', $license),
                    ],
                ];
            })->all(),
            'pagination' => [
                'has_pages' => $licenses->hasPages(),
                'previous_url' => $licenses->previousPageUrl(),
                'next_url' => $licenses->nextPageUrl(),
            ],
        ];
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
