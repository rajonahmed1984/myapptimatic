<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates calls from a MyBuilding installation.
 *
 * Unlike licence verification this endpoint changes data (building size and
 * the per-flat amount billed), so a valid signature is always required.
 * The installation signs with the same shared secret this app uses to call
 * it (MYBUILDING_PROVISION_SECRET); AI_VERIFY_SECRET is accepted too because
 * installations reuse one secret for both directions.
 *
 *   X-Timestamp: unix seconds
 *   X-Signature: hex hmac_sha256("{timestamp}.{rawBody}", secret)
 */
class VerifyMyBuildingSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secrets = array_values(array_unique(array_filter([
            (string) config('mybuilding.provision_secret'),
            (string) config('security.license_verify.secret'),
        ])));

        if ($secrets === []) {
            return response()->json(['success' => false, 'message' => 'MyBuilding sync is not configured.'], 503);
        }

        $timestamp = (string) ($request->header('X-Timestamp') ?: $request->header('X-Apptimatic-Timestamp', ''));
        $signature = (string) ($request->header('X-Signature') ?: $request->header('X-Apptimatic-Signature', ''));

        if ($timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            return response()->json(['success' => false, 'message' => 'Signature required.'], 401);
        }

        $maxSkew = max(30, (int) config('security.license_verify.signature_tolerance_seconds', 300));
        if (abs(time() - (int) $timestamp) > $maxSkew) {
            return response()->json(['success' => false, 'message' => 'Signature expired. Check both server clocks.'], 401);
        }

        $payload = $timestamp.'.'.$request->getContent();

        foreach ($secrets as $secret) {
            if (hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
                return $next($request);
            }
        }

        Log::warning('MyBuilding sync rejected: signature mismatch.', ['ip' => $request->ip()]);

        return response()->json(['success' => false, 'message' => 'Invalid signature.'], 401);
    }
}
