<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Setting;
use App\Models\Subscription;
use App\Services\AccessBlockService;
use App\Services\LicenseLifecycleService;
use App\Services\LicenseRealtimeCheckService;
use App\Support\PaginationPayload;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class LicenseController extends Controller
{
    public function __construct(
        private AccessBlockService $accessBlockService,
        private LicenseRealtimeCheckService $licenseRealtimeCheckService
    ) {
    }

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', License::class);

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
                                    $customerQuery->where(function ($customerSearch) use ($search) {
                                        $customerSearch->where('name', 'like', '%'.$search.'%')
                                            ->orWhere('company_name', 'like', '%'.$search.'%');
                                    });
                                });
                        });

                    if (is_numeric($search)) {
                        $inner->orWhere('id', (int) $search);
                    }
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        // One invoice query for the whole page instead of one per subscription.
        $accessBlockedCustomers = $this->accessBlockService->strictBlockMapForSubscriptions(
            collect($licenses->items())->pluck('subscription')
        );
        $realtimeChecks = [];

        foreach ($licenses as $license) {
            $realtimeChecks[$license->id] = $this->licenseRealtimeCheckService->evaluate($license, $accessBlockedCustomers);
        }

        return Inertia::render(
            'Admin/Licenses/Index',
            $this->indexInertiaProps(
                $licenses,
                $realtimeChecks,
                $search,
                $request
            )
        );
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.subscriptions.index')
            ->with('status', 'Open a subscription and use the Licenses section to create a new license.');
    }

    public function store(Request $request)
    {
        $this->authorize('create', License::class);

        $data = $this->validatedLicenseData($request, false);

        $licenseKey = $data['license_key'] ?: License::generateKey();
        $allowedDomain = $this->extractSingleDomain($data['allowed_domains'] ?? null);

        if ($allowedDomain === false) {
            return $this->backWithLicenseFormError($request, 'allowed_domains', 'Only one domain is allowed per license.');
        }

        if (! $allowedDomain) {
            return $this->backWithLicenseFormError($request, 'allowed_domains', 'Allowed domain is required.');
        }

        $normalizedDomain = $this->normalizeDomain($allowedDomain);

        if (! $normalizedDomain) {
            return $this->backWithLicenseFormError($request, 'allowed_domains', 'Invalid domain format. Use only the hostname.');
        }

        $license = License::create([
            'subscription_id' => $data['subscription_id'],
            'product_id' => $data['product_id'],
            'license_key' => $licenseKey,
            'status' => $data['status'],
            'starts_at' => $data['starts_at'],
            'expires_at' => $data['expires_at'] ?? null,
            'auto_suspend_override_until' => $data['auto_suspend_override_until'] ?? null,
            'max_domains' => 1,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($allowedDomain) {
            LicenseDomain::create([
                'license_id' => $license->id,
                'domain' => $normalizedDomain,
                'status' => 'active',
                'verified_at' => Carbon::now(),
            ]);
        }

        return $this->redirectAfterLicenseAction($request, $license, 'License created.');
    }

    public function edit(License $license): RedirectResponse
    {
        $license->loadMissing('subscription');

        return redirect()->to($this->subscriptionEditUrl($license, 'license-'.$license->id));
    }

    public function update(Request $request, License $license)
    {
        $this->authorize('update', $license);

        $data = $this->validatedLicenseData($request, true, $license);

        // Moving to another subscription goes through move(), which checks the
        // target, re-points the product and records the ownership change.
        if ((int) $data['subscription_id'] !== (int) $license->subscription_id) {
            return $this->backWithLicenseFormError($request, 'subscription_id', 'Use Move license to put this license on another subscription.');
        }

        $data['max_domains'] = 1;

        $domainInput = $this->extractSingleDomain($data['allowed_domains'] ?? null);

        if ($domainInput === false) {
            return $this->backWithLicenseFormError($request, 'allowed_domains', 'Only one domain is allowed per license.');
        }

        if (! $domainInput) {
            return $this->backWithLicenseFormError($request, 'allowed_domains', 'Allowed domain is required.');
        }

        $domain = $this->normalizeDomain($domainInput);

        if (! $domain) {
            return $this->backWithLicenseFormError($request, 'allowed_domains', 'Invalid domain format. Use only the hostname or full URL.');
        }

        $previousStatus = (string) $license->status;
        $license->update($data);
        $this->auditStatusChange($request, $license, $previousStatus);

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

        return $this->redirectAfterLicenseAction($request, $license, 'License updated.');
    }

    public function revokeDomain(Request $request, License $license, LicenseDomain $domain)
    {
        $this->authorize('update', $license);

        if ($domain->license_id !== $license->id) {
            abort(404);
        }

        $domain->update([
            'status' => 'revoked',
        ]);

        return $this->redirectAfterLicenseAction($request, $license, 'Domain revoked.');
    }

    public function suspend(Request $request, License $license)
    {
        $this->authorize('update', $license);

        if ((string) $license->status === 'revoked') {
            return $this->redirectAfterLicenseAction($request, $license, null, 'Revoked licenses cannot be suspended.');
        }

        if ((string) $license->status !== 'suspended') {
            $previousStatus = (string) $license->status;
            $license->update(['status' => 'suspended']);
            $this->auditStatusChange($request, $license, $previousStatus);
        }

        return $this->redirectAfterLicenseAction($request, $license, 'License suspended.');
    }

    public function unsuspend(Request $request, License $license)
    {
        $this->authorize('update', $license);

        if ((string) $license->status === 'revoked') {
            return $this->redirectAfterLicenseAction($request, $license, null, 'Revoked licenses cannot be unsuspended.');
        }

        if (in_array((string) $license->status, ['suspended', 'expired'], true)) {
            $previousStatus = (string) $license->status;
            $license->update(['status' => 'active']);
            $this->auditStatusChange($request, $license, $previousStatus);
        }

        return $this->redirectAfterLicenseAction($request, $license, 'License unsuspended.'.$this->resuspensionWarning($license));
    }

    public function reactivate(Request $request, License $license)
    {
        $this->authorize('update', $license);

        if (in_array((string) $license->status, ['revoked', 'expired'], true)) {
            $previousStatus = (string) $license->status;
            $license->update(['status' => 'active']);
            $this->auditStatusChange($request, $license, $previousStatus);
        }

        return $this->redirectAfterLicenseAction($request, $license, 'License reactivated.'.$this->resuspensionWarning($license));
    }

    /**
     * Move a license onto a different subscription — which is how a license
     * changes owner without taking the whole subscription with it.
     */
    public function move(
        Request $request,
        License $license,
        \App\Services\OwnershipMoveService $moveService
    ) {
        $this->authorize('update', $license);

        $data = $request->validate([
            'subscription_id' => [
                'required',
                'exists:subscriptions,id',
                Rule::notIn([$license->subscription_id]),
            ],
            'keep_domains' => ['nullable', 'boolean'],
        ]);

        $target = \App\Models\Subscription::with(['plan', 'customer'])
            ->findOrFail((int) $data['subscription_id']);

        if (in_array((string) $target->status, ['cancelled'], true)) {
            return $this->redirectAfterLicenseAction(
                $request,
                $license,
                null,
                'A license cannot be moved onto a cancelled subscription.'
            );
        }

        $moveService->moveLicense(
            $license,
            $target,
            (bool) ($data['keep_domains'] ?? false),
            $request->user()?->id
        );

        return $this->redirectAfterLicenseAction(
            $request,
            $license,
            sprintf('License moved to %s.', $target->customer?->name ?? 'subscription #'.$target->id)
        );
    }

    public function terminate(Request $request, License $license)
    {
        $this->authorize('update', $license);

        if ((string) $license->status !== 'revoked') {
            $previousStatus = (string) $license->status;
            $license->update(['status' => 'revoked']);
            $this->auditStatusChange($request, $license, $previousStatus);
        }

        return $this->redirectAfterLicenseAction($request, $license, 'License terminated.');
    }

    public function destroy(License $license)
    {
        $this->authorize('delete', $license);

        $license->delete();

        return redirect()->route('admin.licenses.index')
            ->with('status', 'License deleted.');
    }

    public function sync(License $license)
    {
        $this->authorize('update', $license);

        $license->load(['subscription.customer', 'domains']);
        $check = $this->licenseRealtimeCheckService->sync($license);
        $message = $check['is_verified']
            ? 'License sync completed: verified.'
            : 'License sync completed: '.(string) ($check['reason'] ?? 'unverified').'.';

        if (request()->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'data' => [
                    'reason' => $check['reason'] ?? null,
                    'is_verified' => (bool) ($check['is_verified'] ?? false),
                ],
            ]);
        }

        return redirect()->route('admin.licenses.index')
            ->with('status', $message);
    }

    public function reissueKey(Request $request, License $license)
    {
        $this->authorize('update', $license);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $oldKey = $license->license_key;
        do {
            $newKey = License::generateKey();
        } while (License::where('license_key', $newKey)->exists());

        $license->update(['license_key' => $newKey]);

        \App\Models\StatusAuditLog::logChange(
            License::class,
            $license->id,
            '****'.substr((string) $oldKey, -4),
            '****'.substr($newKey, -4),
            'key_reissued',
            $request->user()?->id,
            ['reason' => $data['reason'] ?? null]
        );

        return $this->redirectAfterLicenseAction($request, $license, 'License key reissued. The previous key no longer works.');
    }

    public function issueCertificate(Request $request, License $license, \App\Services\LicenseCertificateService $certificateService)
    {
        $this->authorize('update', $license);

        $cert = $certificateService->issue($license, $request->user()?->id);

        return $this->redirectAfterLicenseAction($request, $license, 'Signed license certificate issued (cert '.$cert->cert_uuid.').');
    }

    public function revokeCertificate(
        Request $request,
        License $license,
        \App\Models\LicenseCertificate $certificate,
        \App\Services\LicenseCertificateService $certificateService
    ) {
        $this->authorize('update', $license);
        abort_unless($certificate->license_id === $license->id, 404);

        $certificateService->revoke($certificate, $request->user()?->id, (string) $request->input('reason', 'manual_revoke'));

        return $this->redirectAfterLicenseAction($request, $license, 'License certificate revoked.');
    }

    public function downloadCertificate(License $license)
    {
        $this->authorize('view', $license);

        $cert = $license->certificates()->where('status', 'active')->latest()->first();

        if (! $cert) {
            return response()->json(['error' => 'No active certificate for this license.'], 404);
        }

        return response()->json([
            'payload' => $cert->payload,
            'signature' => $cert->signature,
            'key_id' => $cert->key_id,
        ]);
    }

    public function syncStatus(License $license)
    {
        $this->authorize('view', $license);

        $syncAt = $license->last_check_at;
        [$syncLabel, $syncClass] = $this->syncBadge($syncAt);

        return response()->json([
            'ok' => true,
            'data' => [
                'last_check_at' => $syncAt?->toDateTimeString(),
                'last_check_ip' => $license->last_check_ip,
                'last_server_check_at' => $license->last_server_check_at?->toDateTimeString(),
                'sync_label' => $syncLabel,
                'sync_class' => $syncClass,
                'display_time' => $syncAt ? $syncAt->format(config('app.datetime_format', 'd-m-Y h:i A')) : 'No sync yet',
            ],
        ]);
    }

    /**
     * Record an admin's status change. A suspension made here is an admin hold
     * that payments and subscription re-activation leave in place.
     */
    private function auditStatusChange(Request $request, License $license, string $previousStatus): void
    {
        $newStatus = (string) $license->status;

        if ($newStatus === $previousStatus) {
            return;
        }

        \App\Models\StatusAuditLog::logChange(
            License::class,
            $license->id,
            $previousStatus,
            $newStatus,
            $newStatus === 'suspended' ? LicenseLifecycleService::ADMIN_HOLD_REASON : 'admin_'.$newStatus,
            $request->user()?->id
        );
    }

    /**
     * The nightly run suspends licenses on subscriptions with a past-due
     * balance, so a manual reactivation would quietly not stick.
     */
    private function resuspensionWarning(License $license): string
    {
        $license->loadMissing('subscription');
        $subscription = $license->subscription;

        if (! $subscription || (string) $license->status !== 'active') {
            return '';
        }

        $overrideActive = $license->auto_suspend_override_until
            && $license->auto_suspend_override_until->endOfDay()->isFuture();

        if ($overrideActive || ! app(LicenseLifecycleService::class)->hasOutstandingBalance($subscription)) {
            return '';
        }

        return ' The subscription still has an unpaid balance, so automation will suspend it again unless you set an auto-suspend override date.';
    }

    /**
     * How recently the installation itself called the verify API. 48 hours
     * matches the window the header's license health count uses.
     *
     * @return array{0: string, 1: string}
     */
    private function syncBadge(?Carbon $lastCheckAt): array
    {
        if (! $lastCheckAt) {
            return ['Never', 'bg-slate-100 text-slate-600'];
        }

        return $lastCheckAt->greaterThanOrEqualTo(now()->subHours(48))
            ? ['Synced', 'bg-emerald-100 text-emerald-700']
            : ['Stale', 'bg-amber-100 text-amber-700'];
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
        array $realtimeChecks,
        string $search,
        Request $request
    ): array {
        $dateFormat = (string) Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));

        return [
            'pageTitle' => 'Licenses',
            'search' => $search,
            'routes' => [
                'index' => route('admin.licenses.index'),
                'manage_subscriptions' => route('admin.subscriptions.index'),
            ],
            'licenses' => collect($licenses->items())->values()->map(function (License $license) use ($realtimeChecks, $dateFormat, $request) {
                $activeDomain = $license->domains->firstWhere('status', 'active');
                $domain = $activeDomain?->domain ?? $license->domains->first()?->domain;
                $subscription = $license->subscription;
                $customer = $subscription?->customer;
                $check = $realtimeChecks[$license->id] ?? [];
                $isBlocked = (bool) ($check['is_access_blocked'] ?? false);
                $verificationLabel = (string) ($check['verification_label'] ?? 'Verified');
                $verificationClass = (string) ($check['verification_class'] ?? 'bg-emerald-100 text-emerald-700');
                $verificationHint = (string) ($check['verification_hint'] ?? 'Active and domain matched');

                $syncAt = $license->last_check_at;
                [$syncLabel, $syncClass] = $this->syncBadge($syncAt);

                $latestOrder = $subscription?->latestOrder;
                $orderNumber = $latestOrder?->order_number ?? $latestOrder?->id;

                return [
                    'id' => $license->id,
                    'customer_name' => (string) ($customer?->name ?? '--'),
                    'customer_company_name' => (string) ($customer?->company_name ?? ''),
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
                    'sync_time_display' => $syncAt ? $syncAt->format(config('app.datetime_format', 'd-m-Y h:i A')) : 'No sync yet',
                    'can_sync' => (bool) ($request->user()?->can('update', $license)),
                    'routes' => [
                        'edit' => route('admin.licenses.edit', $license),
                        'sync' => route('admin.licenses.sync', $license),
                        'sync_status' => route('admin.licenses.sync-status', $license),
                    ],
                ];
            })->all(),
            'pagination' => PaginationPayload::make(
                $licenses,
                route('admin.licenses.index'),
                ['search' => $search]
            ),
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

    private function validatedLicenseData(Request $request, bool $isUpdate, ?License $license = null): array
    {
        $isNestedPayload = is_array($request->input('license'));
        $payload = $isNestedPayload ? ['license' => $request->input('license')] : $request->all();
        $prefix = $isNestedPayload ? 'license.' : '';
        $errorBag = trim((string) $request->input('_error_bag', ''));
        $licenseKeyRules = $isUpdate
            ? ['required', 'string', 'max:255', Rule::unique('licenses', 'license_key')->ignore($license?->id)]
            : ['nullable', 'string', 'max:255', 'unique:licenses,license_key'];

        $validator = Validator::make($payload, [
            $prefix.'subscription_id' => ['required', 'exists:subscriptions,id'],
            $prefix.'product_id' => ['required', 'exists:products,id'],
            $prefix.'license_key' => $licenseKeyRules,
            $prefix.'status' => ['required', Rule::in(['active', 'suspended', 'expired', 'revoked'])],
            $prefix.'starts_at' => ['required', 'date'],
            $prefix.'expires_at' => ['nullable', 'date', 'after_or_equal:'.$prefix.'starts_at'],
            $prefix.'auto_suspend_override_until' => ['nullable', 'date'],
            $prefix.'allowed_domains' => ['required', 'string'],
            $prefix.'notes' => ['nullable', 'string'],
        ]);

        $validated = $errorBag !== ''
            ? $validator->validateWithBag($errorBag)
            : $validator->validate();

        return $isNestedPayload ? $validated['license'] : $validated;
    }

    private function redirectAfterLicenseAction(
        Request $request,
        License $license,
        ?string $status = null,
        ?string $error = null
    ): RedirectResponse {
        if ($request->boolean('return_to_subscription')) {
            $target = trim((string) $request->input('return_target', 'license-'.$license->id));
            if ($target === '' || $target === 'license-create') {
                $target = 'license-'.$license->id;
            }
            $redirect = redirect()->to($this->subscriptionEditUrl($license, $target));

            if ($status !== null) {
                return $redirect->with('status', $status);
            }

            if ($error !== null) {
                return $redirect->with('error', $error);
            }

            return $redirect;
        }

        $redirect = redirect()->route('admin.licenses.edit', $license);

        if ($status !== null) {
            return $redirect->with('status', $status);
        }

        if ($error !== null) {
            return $redirect->with('error', $error);
        }

        return $redirect;
    }

    private function backWithLicenseFormError(Request $request, string $field, string $message): RedirectResponse
    {
        $errorBag = trim((string) $request->input('_error_bag', ''));
        $errorKey = is_array($request->input('license')) ? 'license.'.$field : $field;
        $redirect = back()->withInput();

        return $errorBag !== ''
            ? $redirect->withErrors([$errorKey => $message], $errorBag)
            : $redirect->withErrors([$errorKey => $message]);
    }

    private function subscriptionEditUrl(License $license, ?string $target = null): string
    {
        $url = route('admin.subscriptions.edit', $license->subscription_id);

        if ($target !== null && $target !== '') {
            $url .= '#'.ltrim($target, '#');
        }

        return $url;
    }
}
