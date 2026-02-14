<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\SalesRepresentative;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class RoleLoginController extends Controller
{
    public function showSalesLogin(): View
    {
        return view('sales.auth.login');
    }

    public function loginSales(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $this->loginTrace('ControllerHit', [
            'route' => $request->route()?->getName(),
            'method' => __METHOD__,
            'email' => (string) $request->input('email', ''),
            'guard' => 'sales',
        ]);

        $recaptcha->assertValid($request, 'SALES_LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool) $request->boolean('remember');

        $salesGuard = Auth::guard('sales');

        $this->loginTrace('Attempting', [
            'guard' => 'sales',
            'email' => $credentials['email'],
            'remember' => $remember,
        ]);

        $attempted = $salesGuard->attempt($credentials, $remember);

        $this->loginTrace('AttemptResult', [
            'guard' => 'sales',
            'result' => $attempted,
            'user_id' => $salesGuard->id(),
        ]);

        if (! $attempted) {
            $this->loginTrace('Response', [
                'guard' => 'sales',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'sales.login',
            ]);

            return redirect()
                ->route('sales.login')
                ->withErrors(['email' => 'Invalid credentials'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        $user = $salesGuard->user();

        if (! $user || $user->role !== Role::SALES) {
            $salesGuard->logout();

            $this->loginTrace('Response', [
                'guard' => 'sales',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'sales.login',
                'reason' => 'role_mismatch',
            ]);
            return redirect()
                ->route('sales.login')
                ->withErrors(['email' => 'Access restricted for this account.'])
                ->withInput($request->only('email'));
        }

        $rep = SalesRepresentative::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (! $rep) {
            $salesGuard->logout();

            $this->loginTrace('Response', [
                'guard' => 'sales',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'sales.login',
                'reason' => 'rep_inactive_or_missing',
            ]);
            return redirect()
                ->route('sales.login')
                ->withErrors(['email' => 'Access restricted for this account.'])
                ->withInput($request->only('email'));
        }

        $this->loginTrace('Response', [
            'guard' => 'sales',
            'type' => 'redirect',
            'status' => 302,
            'target_route' => 'rep.dashboard',
        ]);

        return redirect()->route('rep.dashboard');
    }

    public function logoutSales(Request $request): RedirectResponse
    {
        Auth::guard('sales')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('sales.login');
    }

    public function showSupportLogin(): View
    {
        return view('support.auth.login');
    }

    public function loginSupport(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $this->loginTrace('ControllerHit', [
            'route' => $request->route()?->getName(),
            'method' => __METHOD__,
            'email' => (string) $request->input('email', ''),
            'guard' => 'support',
        ]);

        $recaptcha->assertValid($request, 'SUPPORT_LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool) $request->boolean('remember');

        $supportGuard = Auth::guard('support');

        $this->loginTrace('Attempting', [
            'guard' => 'support',
            'email' => $credentials['email'],
            'remember' => $remember,
        ]);

        $attempted = $supportGuard->attempt($credentials, $remember);

        $this->loginTrace('AttemptResult', [
            'guard' => 'support',
            'result' => $attempted,
            'user_id' => $supportGuard->id(),
        ]);

        if (! $attempted) {
            $this->loginTrace('Response', [
                'guard' => 'support',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'support.login',
            ]);

            return redirect()
                ->route('support.login')
                ->withErrors(['email' => 'Invalid credentials'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        $user = $supportGuard->user();

        if (! $user || $user->role !== Role::SUPPORT) {
            $supportGuard->logout();

            $this->loginTrace('Response', [
                'guard' => 'support',
                'type' => 'redirect',
                'status' => 302,
                'target_route' => 'support.login',
                'reason' => 'role_mismatch',
            ]);
            return redirect()
                ->route('support.login')
                ->withErrors(['email' => 'Access restricted for this account.'])
                ->withInput($request->only('email'));
        }

        $this->loginTrace('Response', [
            'guard' => 'support',
            'type' => 'redirect',
            'status' => 302,
            'target_route' => 'support.dashboard',
        ]);

        return redirect()->route('support.dashboard');
    }

    public function logoutSupport(Request $request): RedirectResponse
    {
        Auth::guard('support')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('support.login');
    }

    private function loginTrace(string $event, array $context = []): void
    {
        if (! config('app.login_trace')) {
            return;
        }

        Log::info('[LOGIN_TRACE] ' . $event, $context);
    }
}
