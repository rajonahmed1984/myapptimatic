<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\RecaptchaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('employee.auth.login');
    }

    public function login(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $this->loginTrace('ControllerHit', [
            'method' => __METHOD__,
            'email' => (string) $request->input('email', ''),
            'guard' => 'employee',
        ]);

        $recaptcha->assertValid($request, 'EMPLOYEE_LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = (bool) $request->boolean('remember');

        $employeeGuard = Auth::guard('employee');

        $this->loginTrace('Attempting', [
            'guard' => 'employee',
            'email' => $credentials['email'],
            'remember' => $remember,
        ]);

        $attempted = $employeeGuard->attempt($credentials, $remember);

        $this->loginTrace('AttemptResult', [
            'guard' => 'employee',
            'result' => $attempted,
            'user_id' => $employeeGuard->id(),
        ]);

        if (! $attempted) {
            $this->loginTrace('Response', [
                'guard' => 'employee',
                'type' => 'redirect',
                'target_route' => 'employee.login',
            ]);

            return redirect()
                ->route('employee.login')
                ->withErrors(['email' => 'Invalid credentials'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        $user = $employeeGuard->user();

        $employee = Employee::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $employee || $employee->status !== 'active') {
            $employeeGuard->logout();

            $this->loginTrace('Response', [
                'guard' => 'employee',
                'type' => 'redirect',
                'target_route' => 'employee.login',
                'reason' => 'employee_inactive_or_missing',
            ]);

            return redirect()
                ->route('employee.login')
                ->withErrors(['email' => 'Access restricted for this account.'])
                ->withInput($request->only('email'));
        }

        $this->loginTrace('Response', [
            'guard' => 'employee',
            'type' => 'redirect',
            'target_route' => 'employee.dashboard',
        ]);

        return redirect()->route('employee.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('employee')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('employee.login');
    }

    private function loginTrace(string $event, array $context = []): void
    {
        if (! config('app.login_trace')) {
            return;
        }

        Log::info('[LOGIN_TRACE] ' . $event, $context);
    }
}
