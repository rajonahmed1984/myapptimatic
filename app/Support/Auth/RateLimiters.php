<?php

namespace App\Support\Auth;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimiters
{
    public const LOGIN_MAX_ATTEMPTS = 5;

    public static function register(): void
    {
        RateLimiter::for('portal-login', function (Request $request) {
            $portal = self::resolvePortal($request);
            $email = (string) $request->input('email', '');

            return Limit::perMinute(self::LOGIN_MAX_ATTEMPTS)
                ->by(self::key($request, $portal, $email))
                ->response(function (Request $request) use ($portal) {
                    return redirect()
                        ->route(Portal::loginRouteName($portal))
                        ->withErrors([
                            'email' => 'Too many login attempts. Please try again in 60 seconds.',
                        ])
                        ->withInput($request->only('email'));
                });
        });
    }

    public static function clear(Request $request, string $portal, string $email): void
    {
        RateLimiter::clear(self::key($request, $portal, $email));
    }

    public static function key(Request $request, string $portal, string $email): string
    {
        $guard = Portal::guardFor($portal);
        $emailKey = strtolower(trim($email));
        $ip = (string) ($request->ip() ?? 'unknown');

        return implode('|', ['portal-login', $portal, $guard, $emailKey, $ip]);
    }

    private static function resolvePortal(Request $request): string
    {
        $portal = $request->route('portal');
        if (is_string($portal) && Portal::isValid($portal)) {
            return $portal;
        }

        return Portal::fromRequestPath($request->path());
    }
}
