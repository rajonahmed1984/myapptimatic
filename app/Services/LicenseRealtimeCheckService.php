<?php

namespace App\Services;

use App\Models\License;
use Carbon\Carbon;

class LicenseRealtimeCheckService
{
    public function __construct(
        private AccessBlockService $accessBlockService
    ) {
    }

    public function evaluate(License $license, array $accessBlockedCustomers = []): array
    {
        $subscription = $license->subscription;
        $customer = $subscription?->customer;
        $activeDomain = $license->domains->firstWhere('status', 'active');
        $autoSuspendOverrideActive = $this->isAutoSuspendOverrideActive($license);

        if (! $customer || (string) $customer->status !== 'active') {
            return $this->blocked('customer_inactive', false);
        }

        if (
            (string) $license->status !== 'active'
            && ! ($autoSuspendOverrideActive && (string) $license->status === 'suspended')
        ) {
            return $this->blocked('license_inactive', false);
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            return $this->blocked('license_expired', false);
        }

        if (
            ! $subscription
            || (
                (string) $subscription->status !== 'active'
                && ! ($autoSuspendOverrideActive && (string) $subscription->status === 'suspended')
            )
        ) {
            return $this->blocked('subscription_inactive', false);
        }

        if (! $activeDomain) {
            return [
                'verification_label' => 'Pending',
                'verification_class' => 'bg-amber-100 text-amber-700',
                'verification_hint' => 'domain_not_bound',
                'is_access_blocked' => false,
                'is_verified' => false,
                'reason' => 'domain_not_bound',
            ];
        }

        $customerId = $customer->id;
        $scopeKey = $customerId.':'.(string) ($license->subscription_id ?? 0);
        $isAccessBlocked = array_key_exists($scopeKey, $accessBlockedCustomers)
            ? (bool) $accessBlockedCustomers[$scopeKey]
            : $this->accessBlockService->isCustomerBlocked($customer, true, $license->subscription_id);

        if ($autoSuspendOverrideActive && $isAccessBlocked) {
            $isAccessBlocked = false;
        }

        if ($isAccessBlocked) {
            return $this->blocked('invoice_overdue', true);
        }

        return [
            'verification_label' => 'Verified',
            'verification_class' => 'bg-emerald-100 text-emerald-700',
            'verification_hint' => 'Active and domain matched',
            'is_access_blocked' => false,
            'is_verified' => true,
            'reason' => null,
        ];
    }

    private function isAutoSuspendOverrideActive(License $license): bool
    {
        $until = $license->auto_suspend_override_until;

        if (! $until) {
            return false;
        }

        return Carbon::now()->lessThanOrEqualTo($until->copy()->endOfDay());
    }

    public function sync(License $license, ?string $ipAddress = null, array $accessBlockedCustomers = []): array
    {
        $result = $this->evaluate($license, $accessBlockedCustomers);

        $updates = [
            'last_check_at' => now(),
            'last_check_ip' => $ipAddress,
        ];

        if ($result['is_verified']) {
            $updates['last_verified_at'] = now();
        }

        $license->update($updates);

        return $result;
    }

    private function blocked(string $reason, bool $isAccessBlocked): array
    {
        return [
            'verification_label' => 'Blocked',
            'verification_class' => 'bg-rose-100 text-rose-700',
            'verification_hint' => $reason,
            'is_access_blocked' => $isAccessBlocked,
            'is_verified' => false,
            'reason' => $reason,
        ];
    }
}
