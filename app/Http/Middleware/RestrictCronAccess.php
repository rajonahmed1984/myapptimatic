<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictCronAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowIps = collect(explode(',', (string) env('CRON_IP_ALLOWLIST', '')))
            ->filter()
            ->map(fn ($ip) => trim($ip))
            ->filter();

        if ($allowIps->isNotEmpty() && ! $allowIps->contains($request->ip())) {
            abort(403, 'Cron access denied (IP).');
        }

        $secret = env('CRON_HMAC_SECRET');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        if (! empty($secret)) {
            if (! is_string($timestamp) || ! is_string($signature)) {
                abort(401, 'Cron signature required.');
            }

            $maxSkew = (int) env('CRON_SIGNATURE_TOLERANCE_SECONDS', 300);
            if ($maxSkew > 0 && abs(time() - (int) $timestamp) > $maxSkew) {
                abort(401, 'Cron signature expired.');
            }

            $payload = $timestamp.'.'.$request->fullUrl();
            $expected = hash_hmac('sha256', $payload, $secret);

            if (! hash_equals($expected, $signature)) {
                abort(401, 'Invalid cron signature.');
            }
        }

        return $next($request);
    }
}
