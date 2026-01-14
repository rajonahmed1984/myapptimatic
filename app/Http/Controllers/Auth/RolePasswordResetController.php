<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class RolePasswordResetController extends Controller
{
    public function showEmployeeForgot(): View|RedirectResponse
    {
        return $this->showForgot('employee');
    }

    public function sendEmployeeResetLink(Request $request): RedirectResponse
    {
        return $this->sendResetLink($request, 'employee');
    }

    public function showEmployeeReset(string $token): View|RedirectResponse
    {
        return $this->showResetForm('employee', $token);
    }

    public function resetEmployee(Request $request): RedirectResponse
    {
        return $this->resetPassword($request, 'employee');
    }

    public function showSalesForgot(): View|RedirectResponse
    {
        return $this->showForgot('sales');
    }

    public function sendSalesResetLink(Request $request): RedirectResponse
    {
        return $this->sendResetLink($request, 'sales');
    }

    public function showSalesReset(string $token): View|RedirectResponse
    {
        return $this->showResetForm('sales', $token);
    }

    public function resetSales(Request $request): RedirectResponse
    {
        return $this->resetPassword($request, 'sales');
    }

    public function showSupportForgot(): View|RedirectResponse
    {
        return $this->showForgot('support');
    }

    public function sendSupportResetLink(Request $request): RedirectResponse
    {
        return $this->sendResetLink($request, 'support');
    }

    public function showSupportReset(string $token): View|RedirectResponse
    {
        return $this->showResetForm('support', $token);
    }

    public function resetSupport(Request $request): RedirectResponse
    {
        return $this->resetPassword($request, 'support');
    }

    private function showForgot(string $role): View|RedirectResponse
    {
        $config = $this->roleConfig($role);

        if (auth($config['guard'])->check()) {
            return redirect()->route($config['dashboard_route']);
        }

        return view($config['forgot_view'], [
            'loginRoute' => route($config['login_route']),
        ]);
    }

    private function showResetForm(string $role, string $token): View|RedirectResponse
    {
        $config = $this->roleConfig($role);

        if (auth($config['guard'])->check()) {
            return redirect()->route($config['dashboard_route']);
        }

        return view($config['reset_view'], [
            'token' => $token,
            'loginRoute' => route($config['login_route']),
        ]);
    }

    private function sendResetLink(Request $request, string $role): RedirectResponse
    {
        $config = $this->roleConfig($role);

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::broker($config['broker'])->sendResetLink([
            'email' => $data['email'],
            'role' => $config['role'],
        ]);

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors(['email' => __($status)]);
        }

        return back()->with('status', 'If your email is in our system, we have sent a reset link.');
    }

    private function resetPassword(Request $request, string $role): RedirectResponse
    {
        $config = $this->roleConfig($role);

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker($config['broker'])->reset(
            array_merge(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                ['role' => $config['role']]
            ),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => $password,
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route($config['login_route'])
                ->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }

    private function roleConfig(string $role): array
    {
        return match ($role) {
            'employee' => [
                'role' => Role::EMPLOYEE,
                'guard' => 'employee',
                'broker' => 'employees',
                'login_route' => 'employee.login',
                'dashboard_route' => 'employee.dashboard',
                'forgot_view' => 'auth.employee.forgot-password',
                'reset_view' => 'auth.employee.reset-password',
            ],
            'sales' => [
                'role' => Role::SALES,
                'guard' => 'sales',
                'broker' => 'sales',
                'login_route' => 'sales.login',
                'dashboard_route' => 'rep.dashboard',
                'forgot_view' => 'auth.sales.forgot-password',
                'reset_view' => 'auth.sales.reset-password',
            ],
            default => [
                'role' => Role::SUPPORT,
                'guard' => 'support',
                'broker' => 'support',
                'login_route' => 'support.login',
                'dashboard_route' => 'support.dashboard',
                'forgot_view' => 'auth.support.forgot-password',
                'reset_view' => 'auth.support.reset-password',
            ],
        };
    }
}
