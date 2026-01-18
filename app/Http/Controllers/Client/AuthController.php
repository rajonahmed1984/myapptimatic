<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('client.auth.login');
    }

    public function login(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $recaptcha->assertValid($request, 'CLIENT_LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::guard('web')->attempt($credentials, $remember)) {
            return back()->withErrors(['email' => 'Invalid credentials'])->withInput();
        }

        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        // Verify user is a client
        if (! $user || $user->role !== 'customer') {
            Auth::guard('web')->logout();
            return back()->withErrors(['email' => 'Access restricted for this account.'])->withInput();
        }

        return redirect()->route('client.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('client.login');
    }
}
