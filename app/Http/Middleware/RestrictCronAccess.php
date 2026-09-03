<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictCronAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowIps = collect(explode(',', (string) config('security.cron.ip_allowlist', '')))
            ->filter()
            ->map(fn ($ip) => trim($ip))
            ->filter();

        if ($allowIps->isNotEmpty() && ! $allowIps->contains($request->ip())) {
            abort(403, 'Cron access denied (IP).');
        }

        if (! $request->hasValidSignature()) {
            abort(401, 'Invalid cron signature.');
        }

        $secret = config('security.cron.hmac_secret');
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        if (! empty($secret)) {
            if (! is_string($timestamp) || ! is_string($signature)) {
                abort(401, 'Cron signature required.');
            }

            $maxSkew = (int) config('security.cron.signature_tolerance_seconds', 300);
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
