<?php

namespace App\Http\Controllers\AuthFresh;

use App\Http\Controllers\Controller;
use App\Services\AuthFresh\LoginService;
use App\Support\AuthFresh\AdminAccess;
use App\Support\AuthFresh\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PortalLoginController extends Controller
{
    public function __construct(
        private readonly LoginService $loginService
    ) {
    }

    public function show(Request $request, string $portal): InertiaResponse|RedirectResponse
    {
        $portal = Portal::normalize($portal);
        Portal::setPortal($request, $portal);

        $guard = Portal::guard($portal);
        $authGuard = Auth::guard($guard);
        if ($authGuard->check()) {
            if (! $this->isSessionBackedAuthentication($request, $authGuard)) {
                $authGuard->logout();
            } elseif ($this->shouldRedirectForPortal($portal, $authGuard->user())) {
                return redirect($this->loginService->defaultRedirectUrlFor($portal, $authGuard->user()));
            }
        }

        return Inertia::render('Auth/PortalLogin', $this->showInertiaProps($request, $portal));
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

    /**
     * @return array<string, mixed>
     */
    private function showInertiaProps(Request $request, string $portal): array
    {
        $titleByPortal = [
            'web' => 'Client Sign In',
            'admin' => 'Admin Sign In',
            'employee' => 'Employee Login',
            'sales' => 'Sales Login',
            'support' => 'Support Login',
        ];

        $forgotByPortal = [
            'web' => route('password.request', [], false),
            'admin' => route('admin.password.request', [], false),
            'employee' => route('employee.password.request', [], false),
            'sales' => route('sales.password.request', [], false),
            'support' => route('support.password.request', [], false),
        ];

        $submitByPortal = [
            'web' => route('login.attempt', [], false),
            'admin' => route('admin.login.attempt', [], false),
            'employee' => route('employee.login.attempt', [], false),
            'sales' => route('sales.login.attempt', [], false),
            'support' => route('support.login.attempt', [], false),
        ];

        $hintByPortal = [
            'web' => ['label' => 'Need an account?', 'href' => route('register', $request->query('redirect') ? ['redirect' => $request->query('redirect')] : [], false), 'text' => 'Register'],
            'admin' => ['label' => 'Client account? Use the', 'href' => route('login', [], false), 'text' => 'client login'],
            'employee' => ['label' => 'Back to', 'href' => route('login', [], false), 'text' => 'main login'],
            'sales' => ['label' => 'Back to', 'href' => route('login', [], false), 'text' => 'main login'],
            'support' => ['label' => 'Back to', 'href' => route('login', [], false), 'text' => 'main login'],
        ];

        return [
            'pageTitle' => $titleByPortal[$portal] ?? 'Sign In',
            'portal' => $portal,
            'form' => [
                'email' => (string) old('email', ''),
                'remember' => (bool) old('remember', false),
                'redirect' => $portal === 'web' ? (string) $request->query('redirect', '') : '',
            ],
            'routes' => [
                'submit' => $submitByPortal[$portal] ?? route('login.attempt', [], false),
                'forgot' => $forgotByPortal[$portal] ?? null,
            ],
            'hint' => $hintByPortal[$portal] ?? null,
            'recaptcha' => [
                'enabled' => (bool) config('recaptcha.enabled'),
                'site_key' => config('recaptcha.site_key'),
                'action' => Portal::recaptchaAction($portal),
            ],
        ];
    }

    private function isSessionBackedAuthentication(Request $request, mixed $guard): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        if (method_exists($guard, 'getName')) {
            $sessionKey = (string) $guard->getName();
            if ($sessionKey !== '' && ! $request->session()->has($sessionKey)) {
                return false;
            }
        }

        if (method_exists($guard, 'viaRemember') && $guard->viaRemember()) {
            return false;
        }

        return true;
    }

    private function shouldRedirectForPortal(string $portal, mixed $user): bool
    {
        if ($portal === 'admin' && ! AdminAccess::canAccess($user)) {
            return false;
        }

        return true;
    }
}
