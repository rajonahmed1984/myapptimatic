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
        // Detect the active authenticated guard BEFORE logging out / invalidating
        // the session, so we can redirect to the correct portal login page.
        // We cannot rely on the session-stored portal because it may be stale
        // (e.g. 'support' stored from a previous admin visit while the user is
        // actually a client on the 'web' guard), which caused the wrong redirect.
        $portal = $this->resolvePortalFromActiveGuard() ?? Portal::fromRequest($request);

        foreach (Portal::guards() as $guard) {
            Auth::guard($guard)->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(Portal::portalLoginUrl($portal));
    }

    /**
     * Walk every known portal definition and return the first portal whose
     * guard has an authenticated user.  Returns null if nobody is logged in
     * (e.g. already-expired session), in which case the caller falls back to
     * detecting from the request path / referer.
     */
    private function resolvePortalFromActiveGuard(): ?string
    {
        foreach (Portal::map() as $portal => $definition) {
            if (Auth::guard($definition['guard'])->check()) {
                return $portal;
            }
        }

        return null;
    }
}
