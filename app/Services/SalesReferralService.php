<?php

namespace App\Services;

use App\Http\Middleware\CaptureSalesReferral;
use App\Models\Customer;
use App\Models\SalesRepresentative;
use App\Support\SystemLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Credits new customers to the sales rep whose referral link brought them.
 *
 * The rep becomes the customer's default sales rep, which CommissionService
 * already falls back to, so commission on the customer's paid invoices flows
 * through the normal sales rep earnings without anything referral-specific.
 */
class SalesReferralService
{
    /**
     * The active rep behind the visitor's referral cookie or ?ref link.
     */
    public function referringRep(Request $request): ?SalesRepresentative
    {
        $code = strtoupper(trim((string) ($request->query('ref') ?: $request->cookie(CaptureSalesReferral::COOKIE, ''))));

        if ($code === '') {
            return null;
        }

        return SalesRepresentative::query()
            ->where('referral_code', $code)
            ->where('status', 'active')
            ->first();
    }

    public function creditNewCustomer(Customer $customer, Request $request): ?SalesRepresentative
    {
        $rep = $this->referringRep($request);

        if (! $rep) {
            return null;
        }

        $customer->forceFill([
            'referred_by_sales_rep_id' => $rep->id,
            'default_sales_rep_id' => $customer->default_sales_rep_id ?: $rep->id,
        ])->save();

        Cookie::queue(Cookie::forget(CaptureSalesReferral::COOKIE));

        SystemLogger::write('activity', 'Customer signed up through a sales rep referral.', [
            'customer_id' => $customer->id,
            'sales_representative_id' => $rep->id,
            'referral_code' => $rep->referral_code,
        ]);

        return $rep;
    }
}
