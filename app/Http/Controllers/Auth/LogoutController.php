<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function logout(Request $request): RedirectResponse
    {
        $portal = $this->resolvePortal($request);

        foreach (Portal::guardNames() as $guard) {
            Auth::guard($guard)->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route(Portal::loginRouteName($portal));
    }

    private function resolvePortal(Request $request): string
    {
        $sessionPortal = Portal::getPortalFromSession($request);
        if ($sessionPortal !== null) {
            return $sessionPortal;
        }

        $routePortal = $request->route('portal');
        if (is_string($routePortal) && Portal::isValid($routePortal)) {
            return $routePortal;
        }

        return Portal::fromRequestPath($request->path());
    }
}
