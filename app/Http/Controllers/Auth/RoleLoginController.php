<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\SalesRepresentative;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RoleLoginController extends Controller
{
    public function showSalesLogin(): View
    {
        return view('sales.auth.login');
    }

    public function loginSales(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $recaptcha->assertValid($request, 'SALES_LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::guard('sales')->attempt($credentials, $remember)) {
            return redirect()
                ->route('sales.login')
                ->withErrors(['email' => 'Invalid credentials'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        $user = Auth::guard('sales')->user();

        if (! $user || $user->role !== Role::SALES) {
            Auth::guard('sales')->logout();
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
            Auth::guard('sales')->logout();
            return redirect()
                ->route('sales.login')
                ->withErrors(['email' => 'Access restricted for this account.'])
                ->withInput($request->only('email'));
        }

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
        $recaptcha->assertValid($request, 'SUPPORT_LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::guard('support')->attempt($credentials, $remember)) {
            return redirect()
                ->route('support.login')
                ->withErrors(['email' => 'Invalid credentials'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        $user = Auth::guard('support')->user();

        if (! $user || $user->role !== Role::SUPPORT) {
            Auth::guard('support')->logout();
            return redirect()
                ->route('support.login')
                ->withErrors(['email' => 'Access restricted for this account.'])
                ->withInput($request->only('email'));
        }

        return redirect()->route('support.dashboard');
    }

    public function logoutSupport(Request $request): RedirectResponse
    {
        Auth::guard('support')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('support.login');
    }
}
