<?php

namespace App\Http\Controllers\AuthFresh;

use App\Http\Controllers\Controller;
use App\Support\AuthFresh\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function logout(Request $request): RedirectResponse
    {
        $portal = $this->resolvePortal($request);

        foreach (Portal::guards() as $guard) {
            Auth::guard($guard)->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(Portal::portalLoginUrl($portal));
    }

    private function resolvePortal(Request $request): string
    {
        $sessionPortal = Portal::sessionPortal($request);
        if ($sessionPortal !== null) {
            $guard = Portal::guard($sessionPortal);
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                if ($sessionPortal === 'admin') {
                    if (\App\Support\AuthFresh\AdminAccess::canAccess($user)) {
                        return 'admin';
                    }
                } else {
                    return $sessionPortal;
                }
            }
        }

        if (Auth::guard('employee')->check()) {
            return 'employee';
        }
        if (Auth::guard('sales')->check()) {
            return 'sales';
        }
        if (Auth::guard('support')->check()) {
            return 'support';
        }
        if (Auth::guard('web')->check()) {
            $webUser = Auth::guard('web')->user();
            if (\App\Support\AuthFresh\AdminAccess::canAccess($webUser)) {
                return 'admin';
            }
            return 'web';
        }

        return Portal::fromRequest($request);
    }
}
