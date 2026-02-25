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
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class RolePasswordResetController extends Controller
{
    public function showEmployeeForgot(): InertiaResponse|RedirectResponse
    {
        $config = $this->roleConfig('employee');

        if (auth($config['guard'])->check()) {
            return redirect()->route($config['dashboard_route']);
        }

        return Inertia::render('Auth/ForgotPassword', $this->forgotPasswordProps('employee', $config));
    }

    public function sendEmployeeResetLink(Request $request): RedirectResponse
    {
        return $this->sendResetLink($request, 'employee');
    }

    public function showEmployeeReset(Request $request, string $token): InertiaResponse|RedirectResponse
    {
        $config = $this->roleConfig('employee');

        if (auth($config['guard'])->check()) {
            return redirect()->route($config['dashboard_route']);
        }

        return Inertia::render('Auth/ResetPassword', $this->resetPasswordProps('employee', $config, $request, $token));
    }

    public function resetEmployee(Request $request): RedirectResponse
    {
        return $this->resetPassword($request, 'employee');
    }

    public function showSalesForgot(): InertiaResponse|RedirectResponse
    {
        $config = $this->roleConfig('sales');

        if (auth($config['guard'])->check()) {
            return redirect()->route($config['dashboard_route']);
        }

        return Inertia::render('Auth/ForgotPassword', $this->forgotPasswordProps('sales', $config));
    }

    public function sendSalesResetLink(Request $request): RedirectResponse
    {
        return $this->sendResetLink($request, 'sales');
    }

    public function showSalesReset(Request $request, string $token): InertiaResponse|RedirectResponse
    {
        $config = $this->roleConfig('sales');

        if (auth($config['guard'])->check()) {
            return redirect()->route($config['dashboard_route']);
        }

        return Inertia::render('Auth/ResetPassword', $this->resetPasswordProps('sales', $config, $request, $token));
    }

    public function resetSales(Request $request): RedirectResponse
    {
        return $this->resetPassword($request, 'sales');
    }

    public function showSupportForgot(): InertiaResponse|RedirectResponse
    {
        $config = $this->roleConfig('support');

        if (auth($config['guard'])->check()) {
            return redirect()->route($config['dashboard_route']);
        }

        return Inertia::render('Auth/ForgotPassword', $this->forgotPasswordProps('support', $config));
    }

    public function sendSupportResetLink(Request $request): RedirectResponse
    {
        return $this->sendResetLink($request, 'support');
    }

    public function showSupportReset(Request $request, string $token): InertiaResponse|RedirectResponse
    {
        $config = $this->roleConfig('support');

        if (auth($config['guard'])->check()) {
            return redirect()->route($config['dashboard_route']);
        }

        return Inertia::render('Auth/ResetPassword', $this->resetPasswordProps('support', $config, $request, $token));
    }

    public function resetSupport(Request $request): RedirectResponse
    {
        return $this->resetPassword($request, 'support');
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function forgotPasswordProps(string $role, array $config): array
    {
        return [
            'pageTitle' => match ($role) {
                'employee' => 'Employee Password Reset',
                'sales' => 'Sales Password Reset',
                default => 'Support Password Reset',
            },
            'form' => [
                'email' => old('email', ''),
            ],
            'routes' => [
                'email' => route($config['password_email_route'], [], false),
                'login' => route($config['login_route'], [], false),
            ],
            'messages' => [
                'status' => session('status'),
                'email_error_warning' => true,
            ],
            'recaptcha' => [
                'enabled' => false,
                'site_key' => '',
                'action' => '',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function resetPasswordProps(string $role, array $config, Request $request, string $token): array
    {
        return [
            'pageTitle' => match ($role) {
                'employee' => 'Employee Reset Password',
                'sales' => 'Sales Reset Password',
                default => 'Support Reset Password',
            },
            'form' => [
                'token' => $token,
                'email' => old('email', (string) $request->query('email', '')),
            ],
            'routes' => [
                'submit' => route($config['password_update_route'], [], false),
                'login' => route($config['login_route'], [], false),
            ],
            'messages' => [
                'status' => session('status'),
            ],
        ];
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
                'password_email_route' => 'employee.password.email',
                'password_update_route' => 'employee.password.update',
            ],
            'sales' => [
                'role' => Role::SALES,
                'guard' => 'sales',
                'broker' => 'sales',
                'login_route' => 'sales.login',
                'dashboard_route' => 'rep.dashboard',
                'password_email_route' => 'sales.password.email',
                'password_update_route' => 'sales.password.update',
            ],
            default => [
                'role' => Role::SUPPORT,
                'guard' => 'support',
                'broker' => 'support',
                'login_route' => 'support.login',
                'dashboard_route' => 'support.dashboard',
                'password_email_route' => 'support.password.email',
                'password_update_route' => 'support.password.update',
            ],
        };
    }
}
