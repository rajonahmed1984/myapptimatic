<?php

namespace App\Http\Middleware;

use App\Models\SalesRepresentative;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers which sales rep sent a visitor. Any page opened with ?ref=CODE
 * stores the code for 30 days; signing up as a customer later credits the
 * rep (see SalesReferralService). The latest valid link wins.
 */
class CaptureSalesReferral
{
    public const COOKIE = 'sales_ref';

    public const MINUTES = 60 * 24 * 30;

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->isMethod('GET') ? trim((string) $request->query('ref', '')) : '';

        if ($code !== '' && strlen($code) <= 20) {
            $isActiveRep = SalesRepresentative::query()
                ->where('referral_code', strtoupper($code))
                ->where('status', 'active')
                ->exists();

            if ($isActiveRep) {
                Cookie::queue(self::COOKIE, strtoupper($code), self::MINUTES);
            }
        }

        return $next($request);
    }
}
