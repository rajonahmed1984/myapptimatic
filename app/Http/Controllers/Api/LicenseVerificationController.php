<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\EvaluateLicenseRiskJob;
use App\Models\Invoice;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Setting;
use App\Events\LicenseBlocked;
use App\Events\LicenseVerified;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\AccessBlockService;

class LicenseVerificationController extends Controller
{
    public function __construct(
        private AccessBlockService $accessBlockService
    ) {
    }

    public function verify(Request $request)
    {
        $requestId = (string) Str::uuid();
        $signatureValid = (bool) $request->attributes->get('api_signature_valid', false);
        $includeSensitive = (bool) env('COMPAT_LEGACY_LICENSE_RESPONSE', true);
        $autoBindOverride = env('AUTO_BIND_DOMAINS_ENABLED');
        $requireSignedBind = (bool) env('AUTO_BIND_REQUIRE_SIGNATURE', false);

        $data = $request->validate([
            'license_key' => ['required', 'string'],
            'domain' => ['required', 'string'],
            'license_url' => ['nullable', 'string'],
        ]);

        $decision = 'allow';
        $reason = null;
        $domainInput = (string) ($data['domain'] ?? '');

        $license = License::query()
            ->with(['subscription.customer', 'domains'])
            ->where('license_key', $data['license_key'])
            ->first();

        if (! $license) {
            $decision = 'block';
            $reason = 'license_not_found';
            $this->logUsage($requestId, $decision, $reason, null, null, null, $domainInput, $request);

            return $this->blockedResponse($reason, [], $requestId);
        }

        $customer = $license->subscription->customer;

        if (! $customer || $customer->status !== 'active') {
            $decision = 'block';
            $reason = 'customer_inactive';
            $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domainInput, $request);

            return $this->blockedResponse($reason, [], $requestId);
        }

        if ($license->status !== 'active') {
            $decision = 'block';
            $reason = 'license_inactive';
            $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domainInput, $request);

            return $this->blockedResponse($reason, [], $requestId);
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            $decision = 'block';
            $reason = 'license_expired';
            $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domainInput, $request);

            return $this->blockedResponse($reason, [], $requestId);
        }

        if ($license->subscription->status !== 'active') {
            $decision = 'block';
            $reason = 'subscription_inactive';
            $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domainInput, $request);

            return $this->blockedResponse($reason, [], $requestId);
        }

        $domain = $this->normalizeDomain($domainInput);
        if (! $domain) {
            $decision = 'block';
            $reason = 'invalid_domain';
            $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domainInput, $request);

            return $this->blockedResponse($reason, [], $requestId);
        }

        $licenseUrlInput = trim((string) ($data['license_url'] ?? ''));
        if ($licenseUrlInput !== '') {
            $licenseUrlDomain = $this->normalizeDomain($licenseUrlInput);

            if (! $licenseUrlDomain || $licenseUrlDomain !== $domain) {
                $decision = 'block';
                $reason = 'invalid_domain';
                $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domain, $request, [
                    'license_url_mismatch' => true,
                ]);

                return $this->blockedResponse($reason, [], $requestId);
            }
        }

        $activeDomain = LicenseDomain::query()
            ->where('license_id', $license->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        if ($activeDomain) {
            $extraIds = LicenseDomain::query()
                ->where('license_id', $license->id)
                ->where('status', 'active')
                ->where('id', '!=', $activeDomain->id)
                ->pluck('id');

            if ($extraIds->isNotEmpty()) {
                LicenseDomain::query()
                    ->whereIn('id', $extraIds)
                    ->update(['status' => 'revoked']);
            }

            if (strtolower($activeDomain->domain) !== $domain) {
                $decision = 'block';
                $reason = 'domain_not_allowed';
                $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domain, $request);

                return $this->blockedResponse($reason, [], $requestId);
            }

            $activeDomain->update([
                'last_seen_at' => Carbon::now(),
            ]);
        } else {
            $autoBindSetting = (bool) Setting::getValue('auto_bind_domains');
            $autoBind = is_null($autoBindOverride) ? $autoBindSetting : (bool) $autoBindOverride;

            if (! $autoBind) {
                $decision = 'block';
                $reason = 'domain_not_allowed';
                $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domain, $request);

                return $this->blockedResponse($reason, [], $requestId);
            }

            if ($requireSignedBind && ! $signatureValid) {
                $decision = 'block';
                $reason = 'domain_not_allowed';
                $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domain, $request);

                return $this->blockedResponse($reason);
            }

            $existingDomain = LicenseDomain::query()
                ->where('license_id', $license->id)
                ->where('domain', $domain)
                ->first();

            if ($existingDomain) {
                $existingDomain->update([
                    'status' => 'active',
                    'verified_at' => Carbon::now(),
                    'last_seen_at' => Carbon::now(),
                ]);
            } else {
                LicenseDomain::create([
                    'license_id' => $license->id,
                    'domain' => $domain,
                    'status' => 'active',
                    'verified_at' => Carbon::now(),
                    'last_seen_at' => Carbon::now(),
                ]);
            }
        }

        $invoiceBlock = $this->accessBlockService->invoiceBlockStatus($customer);

        $license->update([
            'last_check_at' => Carbon::now(),
            'last_verified_at' => Carbon::now(),
            'last_check_ip' => $request->ip(),
        ]);

        if ($invoiceBlock['blocked']) {
            $blockPayload = $invoiceBlock;

            if (! $includeSensitive) {
                $blockPayload['payment_url'] = null;
                $blockPayload['invoice_id'] = null;
                $blockPayload['invoice_number'] = null;
            }

            $decision = 'block';
            $reason = $invoiceBlock['reason'];
            $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domain, $request, [
                'invoice_status' => $invoiceBlock['invoice_status'] ?? null,
            ]);

            LicenseBlocked::dispatch($license, $reason, ['request_id' => $requestId]);

            return $this->blockedResponse($invoiceBlock['reason'], $blockPayload, $requestId);
        }

        // If invoice due but not blocked, mark as warn
        if ($invoiceBlock['reason'] === 'invoice_due') {
            $decision = 'warn';
            $reason = 'invoice_due';
        }

        $usageLogId = $this->logUsage($requestId, $decision, $reason, $license, $license->subscription, $customer, $domain, $request, [
            'notice' => $invoiceBlock['reason'],
        ]);

        LicenseVerified::dispatch($license, [
            'request_id' => $requestId,
            'notice' => $invoiceBlock['reason'],
        ]);

        if ($usageLogId && env('AI_LICENSE_RISK_ENABLED', false)) {
            dispatch((new EvaluateLicenseRiskJob($usageLogId))->onQueue('ai'));
        }

        $response = response()->json([
            'status' => 'active',
            'blocked' => false,
            'notice' => $invoiceBlock['reason'] === 'invoice_due' ? 'invoice_due' : null,
            'license_id' => $license->id,
            'product_id' => $license->product_id,
            'customer_id' => $customer->id,
            'domain' => $domain,
            'grace_ends_at' => $includeSensitive ? $invoiceBlock['grace_ends_at'] : null,
            'payment_url' => $includeSensitive ? $invoiceBlock['payment_url'] : null,
            'invoice_id' => $includeSensitive ? $invoiceBlock['invoice_id'] : null,
            'invoice_number' => $includeSensitive ? $invoiceBlock['invoice_number'] : null,
            'invoice_status' => $includeSensitive ? $invoiceBlock['invoice_status'] : null,
            'request_id' => $requestId,
        ]);

        if ($requestId) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
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

    private function blockedResponse(string $reason, array $payload = [], ?string $requestId = null)
    {
        $response = response()->json(array_merge([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => $reason,
            'payment_url' => $payload['payment_url'] ?? null,
            'grace_ends_at' => $payload['grace_ends_at'] ?? null,
            'request_id' => $requestId,
        ], $payload));

        if ($requestId) {
            $response->headers->set('X-Request-Id', $requestId);
        }

        return $response;
    }

    private function logUsage(
        string $requestId,
        string $decision,
        ?string $reason,
        ?License $license,
        ?\App\Models\Subscription $subscription,
        ?\App\Models\Customer $customer,
        ?string $domain,
        Request $request,
        array $metadata = []
    ): ?int {
        try {
            $id = DB::table('license_usage_logs')->insertGetId([
                'license_id' => $license?->id,
                'subscription_id' => $subscription?->id,
                'customer_id' => $customer?->id,
                'domain' => $domain,
                'device_id' => $request->input('device_id'),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'request_id' => $requestId,
                'action' => 'verify',
                'decision' => $decision,
                'reason' => $reason,
                'metadata' => $metadata ? json_encode($metadata) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        } catch (\Throwable) {
            // Logging failures must not block verification flow.
            return null;
        }
    }
}
