<?php

namespace App\Http\Controllers\AuthFresh;

use App\Http\Controllers\Controller;
use App\Services\AuthFresh\LoginService;
use App\Support\AuthFresh\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PortalLoginController extends Controller
{
    public function __construct(
        private readonly LoginService $loginService
    ) {
    }

    public function show(Request $request, string $portal): View|RedirectResponse
    {
        $portal = Portal::normalize($portal);
        Portal::setPortal($request, $portal);

        $guard = Portal::guard($portal);
        if (Auth::guard($guard)->check()) {
            return redirect($this->loginService->defaultRedirectUrlFor($portal, Auth::guard($guard)->user()));
        }

        return view(Portal::loginView($portal));
    }

    public function login(Request $request, string $portal): RedirectResponse
    {
        $portal = Portal::normalize($portal);
        $result = $this->loginService->authenticate($request, $portal);

        if (! ($result['ok'] ?? false)) {
            return redirect(Portal::portalLoginUrl($portal))
                ->withErrors(['email' => $result['error'] ?? 'Invalid credentials'])
                ->withInput($request->only('email'));
        }

        return redirect()->intended((string) ($result['redirect'] ?? Portal::portalLoginUrl($portal)));
    }
}
