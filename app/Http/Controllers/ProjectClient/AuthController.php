<?php

namespace App\Http\Controllers\ProjectClient;

use App\Http\Controllers\Controller;
use App\Services\RecaptchaService;
use App\Support\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuthController extends Controller
{
    public function showLogin(): InertiaResponse
    {
        return Inertia::render('Auth/ProjectLogin', [
            'form' => [
                'email' => old('email', ''),
                'remember' => (bool) old('remember', false),
            ],
            'routes' => [
                'submit' => route('project-client.login.attempt', [], false),
            ],
            'recaptcha' => [
                'enabled' => (bool) config('recaptcha.enabled') && is_string(config('recaptcha.site_key')) && config('recaptcha.site_key') !== '',
                'site_key' => (string) config('recaptcha.site_key', ''),
                'action' => 'PROJECT_CLIENT_LOGIN',
            ],
        ]);
    }

    public function login(Request $request, RecaptchaService $recaptcha): RedirectResponse
    {
        $recaptcha->assertValid($request, 'PROJECT_CLIENT_LOGIN');

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            SystemLogger::write('admin', 'Project client login failed.', [
                'login_type' => 'project_client',
                'email' => $credentials['email'],
            ], null, $request->ip(), 'warning');

            return redirect()
                ->route('project-client.login')
                ->withErrors(['email' => 'The provided credentials are incorrect.'])
                ->withInput($request->only('email'));
        }

        $user = $request->user();

        if ($user && $user->isClientProject() && $user->status === 'inactive') {
            SystemLogger::write('admin', 'Project client login denied (inactive).', [
                'login_type' => 'project_client',
                'email' => $user->email,
                'user_id' => $user->id,
            ], $user->id, $request->ip(), 'warning');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('project-client.login')
                ->withErrors(['email' => 'Your account is inactive. Please contact support.'])
                ->withInput($request->only('email'));
        }

        if (! $user || ! $user->isClientProject() || ! $user->project_id) {
            SystemLogger::write('admin', 'Project client login denied.', [
                'login_type' => 'project_client',
                'email' => $user?->email,
                'user_id' => $user?->id,
            ], $user?->id, $request->ip(), 'warning');

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('project-client.login')
                ->withErrors(['email' => 'This account cannot access project tasks.'])
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        SystemLogger::write('admin', 'Project client login.', [
            'email' => $user->email,
            'user_id' => $user->id,
            'project_id' => $user->project_id,
        ], $user->id, $request->ip());

        return redirect()->route('client.projects.show', $user->project_id);
    }
}
