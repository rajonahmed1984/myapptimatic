<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class AffiliateService
{
    const COOKIE_NAME = 'affiliate_ref';
    const COOKIE_DURATION = 30 * 24 * 60; // 30 days in minutes

    /**
     * Track affiliate referral from request
     */
    public function trackReferral(Request $request): ?AffiliateReferral
    {
        $refCode = $request->get('ref');

        if (! $refCode) {
            return null;
        }

        $affiliate = Affiliate::where('affiliate_code', $refCode)
            ->where('status', 'active')
            ->first();

        if (! $affiliate) {
            return null;
        }

        // Store affiliate code in cookie
        Cookie::queue(static::COOKIE_NAME, $refCode, static::COOKIE_DURATION);

        // Track the referral click
        $referral = AffiliateReferral::create([
            'affiliate_id' => $affiliate->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer_url' => $request->headers->get('referer'),
            'landing_page' => $request->fullUrl(),
            'status' => 'pending',
        ]);

        $affiliate->increment('total_referrals');

        return $referral;
    }

    /**
     * Associate customer with affiliate from cookie
     */
    public function associateCustomerWithAffiliate(Customer $customer, Request $request): void
    {
        $refCode = $request->cookie(static::COOKIE_NAME);

        if (! $refCode || $customer->referred_by_affiliate_id) {
            return;
        }

        $affiliate = Affiliate::where('affiliate_code', $refCode)
            ->where('status', 'active')
            ->first();

        if (! $affiliate) {
            return;
        }

        // Link customer to affiliate
        $customer->update(['referred_by_affiliate_id' => $affiliate->id]);

        // Find or create referral and mark as converted
        $referral = AffiliateReferral::where('affiliate_id', $affiliate->id)
            ->whereNull('customer_id')
            ->where('ip_address', $request->ip())
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($referral) {
            $referral->customer_id = $customer->id;
            $referral->markAsConverted();
        } else {
            $referral = AffiliateReferral::create([
                'affiliate_id' => $affiliate->id,
                'customer_id' => $customer->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'converted',
                'converted_at' => now(),
            ]);
            $affiliate->increment('total_conversions');
        }
    }

    /**
     * Create commission for an order
     */
    public function createCommissionForOrder(Order $order): ?AffiliateCommission
    {
        $customer = $order->customer;

        if (! $customer || ! $customer->referred_by_affiliate_id) {
            return null;
        }

        $affiliate = $customer->referredByAffiliate;

        if (! $affiliate || ! $affiliate->isActive()) {
            return null;
        }

        $referral = AffiliateReferral::where('affiliate_id', $affiliate->id)
            ->where('customer_id', $customer->id)
            ->where('status', 'converted')
            ->first();

        $amount = $this->calculateCommission($order->total, $affiliate);

        return AffiliateCommission::create([
            'affiliate_id' => $affiliate->id,
            'referral_id' => $referral?->id,
            'order_id' => $order->id,
            'description' => "Commission for order #{$order->order_number}",
            'amount' => $amount,
            'commission_rate' => $affiliate->commission_rate,
            'status' => 'pending', // Requires admin approval
        ]);
    }

    /**
     * Create commission for an invoice payment
     */
    public function createCommissionForInvoice(Invoice $invoice): ?AffiliateCommission
    {
        $customer = $invoice->customer;

        if (! $customer || ! $customer->referred_by_affiliate_id) {
            return null;
        }

        $affiliate = $customer->referredByAffiliate;

        if (! $affiliate || ! $affiliate->isActive()) {
            return null;
        }

        // Check if commission already exists for this invoice
        $existingCommission = AffiliateCommission::where('invoice_id', $invoice->id)
            ->where('affiliate_id', $affiliate->id)
            ->first();

        if ($existingCommission) {
            return $existingCommission;
        }

        $referral = AffiliateReferral::where('affiliate_id', $affiliate->id)
            ->where('customer_id', $customer->id)
            ->where('status', 'converted')
            ->first();

        $amount = $this->calculateCommission($invoice->total, $affiliate);

        return AffiliateCommission::create([
            'affiliate_id' => $affiliate->id,
            'referral_id' => $referral?->id,
            'invoice_id' => $invoice->id,
            'description' => "Commission for invoice #{$invoice->invoice_number}",
            'amount' => $amount,
            'commission_rate' => $affiliate->commission_rate,
            'status' => 'pending',
        ]);
    }

    /**
     * Calculate commission amount
     */
    protected function calculateCommission(float $baseAmount, Affiliate $affiliate): float
    {
        if ($affiliate->commission_type === 'fixed') {
            return $affiliate->fixed_commission_amount ?? 0;
        }

        return round($baseAmount * ($affiliate->commission_rate / 100), 2);
    }

    /**
     * Get affiliate from request (cookie or query param)
     */
    public function getAffiliateFromRequest(Request $request): ?Affiliate
    {
        $refCode = $request->get('ref') ?: $request->cookie(static::COOKIE_NAME);

        if (! $refCode) {
            return null;
        }

        return Affiliate::where('affiliate_code', $refCode)
            ->where('status', 'active')
            ->first();
    }
}
