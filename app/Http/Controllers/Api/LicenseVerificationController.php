<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\License;
use App\Models\LicenseDomain;
use App\Models\Setting;
use Carbon\Carbon;
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
        $data = $request->validate([
            'license_key' => ['required', 'string'],
            'domain' => ['required', 'string'],
        ]);

        $license = License::query()
            ->with(['subscription.customer', 'domains'])
            ->where('license_key', $data['license_key'])
            ->first();

        if (! $license) {
            return $this->blockedResponse('license_not_found');
        }

        $customer = $license->subscription->customer;

        if (! $customer || $customer->status !== 'active') {
            return $this->blockedResponse('customer_inactive');
        }

        if ($license->status !== 'active') {
            return $this->blockedResponse('license_inactive');
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            return $this->blockedResponse('license_expired');
        }

        if ($license->subscription->status !== 'active') {
            return $this->blockedResponse('subscription_inactive');
        }

        $domain = $this->normalizeDomain($data['domain']);
        if (! $domain) {
            return $this->blockedResponse('invalid_domain');
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
                return $this->blockedResponse('domain_not_allowed');
            }

            $activeDomain->update([
                'last_seen_at' => Carbon::now(),
            ]);
        } else {
            $autoBind = (bool) Setting::getValue('auto_bind_domains');

            if (! $autoBind) {
                return $this->blockedResponse('domain_not_allowed');
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
            'last_check_ip' => $request->ip(),
        ]);

        if ($invoiceBlock['blocked']) {
            return $this->blockedResponse($invoiceBlock['reason'], $invoiceBlock);
        }

        return response()->json([
            'status' => 'active',
            'blocked' => false,
            'notice' => $invoiceBlock['reason'] === 'invoice_due' ? 'invoice_due' : null,
            'license_id' => $license->id,
            'product_id' => $license->product_id,
            'customer_id' => $customer->id,
            'domain' => $domain,
            'grace_ends_at' => $invoiceBlock['grace_ends_at'],
            'payment_url' => $invoiceBlock['payment_url'],
            'invoice_id' => $invoiceBlock['invoice_id'],
            'invoice_number' => $invoiceBlock['invoice_number'],
            'invoice_status' => $invoiceBlock['invoice_status'],
        ]);
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

    private function blockedResponse(string $reason, array $payload = [])
    {
        return response()->json(array_merge([
            'status' => 'blocked',
            'blocked' => true,
            'reason' => $reason,
            'payment_url' => $payload['payment_url'] ?? null,
            'grace_ends_at' => $payload['grace_ends_at'] ?? null,
        ], $payload));
    }
}
