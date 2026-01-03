<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $requireSignature = (bool) env('AI_REQUIRE_SIGNED_VERIFY', false);
        $secret = env('AI_VERIFY_SECRET');

        // If signature not configured, skip verification but continue.
        if (!$requireSignature || empty($secret)) {
            $request->attributes->set('api_signature_valid', false);

            return $next($request);
        }

        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');

        if (! is_string($timestamp) || ! is_string($signature)) {
            abort(401, 'Signature required');
        }

        // Reject stale requests (default 5 minutes).
        $maxSkew = (int) env('API_SIGNATURE_TOLERANCE_SECONDS', 300);
        if ($maxSkew > 0 && abs(time() - (int) $timestamp) > $maxSkew) {
            abort(401, 'Signature expired');
        }

        $body = $request->getContent() ?? '';
        $payload = $timestamp.'.'.$body;
        $expected = hash_hmac('sha256', $payload, $secret ?? '');

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid signature');
        }

        $request->attributes->set('api_signature_valid', true);

        return $next($request);
    }
}
