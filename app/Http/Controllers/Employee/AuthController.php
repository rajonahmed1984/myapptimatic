<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('employee.auth.login');
    }

    public function login(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $recaptcha->assertValid($request, 'EMPLOYEE_LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool) $request->boolean('remember');

        if (! Auth::guard('employee')->attempt($credentials, $remember)) {
            return back()->withErrors(['email' => 'Invalid credentials'])->withInput();
        }

        $request->session()->regenerate();

        $user = Auth::guard('employee')->user();

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $employee || $employee->status !== 'active') {
            Auth::guard('employee')->logout();
            return back()->withErrors(['email' => 'Access restricted for this account.'])->withInput();
        }

        return redirect()->route('employee.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('employee')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('employee.login');
    }
}
