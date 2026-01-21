<?php

namespace App\Http\Controllers\ProjectClient;

use App\Http\Controllers\Controller;
use App\Services\RecaptchaService;
use App\Support\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('project-client.login');
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

            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
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

            throw ValidationException::withMessages([
                'email' => 'Your account is inactive. Please contact support.',
            ]);
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

            throw ValidationException::withMessages([
                'email' => 'This account cannot access project tasks.',
            ]);
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
