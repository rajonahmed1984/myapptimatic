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

class LicenseVerificationController extends Controller
{
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

        $existingDomain = $license->domains->first(
            fn (LicenseDomain $item) => strtolower($item->domain) === $domain && $item->status === 'active'
        );

        $domainAllowed = (bool) $existingDomain;

        if (! $domainAllowed) {
            $autoBind = (bool) Setting::getValue('auto_bind_domains');
            $currentCount = $license->domains->count();

            if ($autoBind && $currentCount < $license->max_domains) {
                LicenseDomain::create([
                    'license_id' => $license->id,
                    'domain' => $domain,
                    'status' => 'active',
                    'verified_at' => Carbon::now(),
                    'last_seen_at' => Carbon::now(),
                ]);
                $domainAllowed = true;
            }
        }

        if ($existingDomain) {
            $existingDomain->update([
                'last_seen_at' => Carbon::now(),
            ]);
        }

        if (! $domainAllowed) {
            return $this->blockedResponse('domain_not_allowed');
        }

        $invoiceBlock = $this->invoiceBlockStatus($customer->id);

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

    private function invoiceBlockStatus(int $customerId): array
    {
        $graceDays = (int) Setting::getValue('grace_period_days');
        $invoice = Invoice::query()
            ->with('customer')
            ->where('customer_id', $customerId)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->orderBy('due_date')
            ->first();

        if (! $invoice) {
            return [
                'blocked' => false,
                'reason' => null,
                'grace_ends_at' => null,
                'payment_url' => null,
                'invoice_id' => null,
                'invoice_number' => null,
                'invoice_status' => null,
            ];
        }

        $graceEnds = Carbon::parse($invoice->due_date)->addDays($graceDays)->endOfDay();
        $blocked = Carbon::now()->greaterThan($graceEnds);

        if ($invoice->customer && $invoice->customer->access_override_until && $invoice->customer->access_override_until->isFuture()) {
            $blocked = false;
        }

        return [
            'blocked' => $blocked,
            'reason' => $blocked ? 'invoice_overdue' : 'invoice_due',
            'grace_ends_at' => $graceEnds->toDateTimeString(),
            'payment_url' => route('client.invoices.pay', $invoice),
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->number,
            'invoice_status' => $invoice->status,
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
