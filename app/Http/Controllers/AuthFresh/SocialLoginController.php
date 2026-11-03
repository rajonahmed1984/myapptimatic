<?php

namespace App\Http\Controllers\AuthFresh;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Enums\Role;
use App\Support\Currency;
use App\Support\AuthFresh\Portal;
use App\Services\AuthFresh\LoginOtpService;
use App\Services\AuthFresh\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SocialLoginController extends Controller
{
    private const PORTAL = 'web';

    public function __construct(
        private readonly LoginService $loginService
    ) {
    }

    /**
     * Redirect the user to the provider's OAuth server.
     */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $provider = strtolower($provider);
        if (! in_array($provider, ['google'], true)) {
            return redirect()->route('login')->with('social_error', 'Unsupported login provider.');
        }

        $portal = Portal::normalize($request->query('portal', 'web'));
        if ($portal !== self::PORTAL) {
            return redirect(Portal::portalLoginUrl($portal))
                ->with('social_error', 'Google login is only available for client accounts.');
        }

        $request->session()->put('social_auth_portal', $portal);
        $request->session()->put('social_auth_provider', $provider);

        $config = config("services.{$provider}");

        if (empty($config['client_id'])) {
            if ($this->sandboxAvailable()) {
                return redirect()->route('auth.sandbox', ['provider' => $provider, 'portal' => $portal]);
            }

            return redirect(Portal::portalLoginUrl($portal))
                ->with('social_error', ucfirst($provider) . ' login is currently unavailable.');
        }

        $state = Str::random(40);
        $request->session()->put("oauth_state_{$provider}", $state);

        $redirectUri = route('auth.callback', ['provider' => $provider]);

        switch ($provider) {
            case 'google':
                $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                    'client_id' => $config['client_id'],
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => 'openid profile email',
                    'state' => $state,
                ]);
                break;

            default:
                return redirect(Portal::portalLoginUrl($portal));
        }

        return redirect($url);
    }

    /**
     * Handle the provider callback.
     */
    public function callback(Request $request, string $provider)
    {
        $provider = strtolower($provider);
        $request->session()->forget('social_auth_portal');
        $portal = self::PORTAL;
        $sessionState = $request->session()->pull("oauth_state_{$provider}");

        $state = $request->input('state');
        if (! is_string($state) || ! is_string($sessionState) || ! hash_equals($sessionState, $state)) {
            return redirect(Portal::portalLoginUrl($portal))
                ->with('social_error', 'Invalid state parameter. Authentication failed.');
        }

        $code = $request->input('code');
        if (empty($code)) {
            return redirect(Portal::portalLoginUrl($portal))
                ->with('social_error', 'Authorization code missing.');
        }

        $config = config("services.{$provider}");
        $redirectUri = route('auth.callback', ['provider' => $provider]);

        try {
            $email = null;
            $name = null;

            if ($provider === 'google') {
                $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'code' => $code,
                    'client_id' => $config['client_id'],
                    'client_secret' => $config['client_secret'],
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                ]);

                if (! $response->successful()) {
                    return redirect(Portal::portalLoginUrl($portal))->with('social_error', 'Token exchange failed.');
                }

                $tokenData = $response->json();
                $accessToken = $tokenData['access_token'] ?? null;

                $userResponse = Http::withToken($accessToken)->get('https://www.googleapis.com/oauth2/v3/userinfo');
                if ($userResponse->successful()) {
                    $userData = $userResponse->json();
                    // Accounts are matched by email, so only trust addresses Google has verified.
                    if (($userData['email_verified'] ?? false) !== true) {
                        return redirect(Portal::portalLoginUrl($portal))
                            ->with('social_error', 'Your Google email address is not verified.');
                    }
                    $email = $userData['email'] ?? null;
                    $name = $userData['name'] ?? null;
                }
            }

            if (empty($email)) {
                return redirect(Portal::portalLoginUrl($portal))
                    ->with('social_error', 'Could not retrieve email address from social account.');
            }

            return $this->authenticateAndRedirect($request, $email, $name ?? explode('@', $email)[0], $portal);

        } catch (\Exception $e) {
            Log::warning('Google login failed.', ['error' => $e->getMessage()]);

            return redirect(Portal::portalLoginUrl($portal))
                ->with('social_error', 'Google sign-in did not complete. Please try again.');
        }
    }

    /**
     * Show the developer sandbox page.
     */
    public function sandbox(Request $request, string $provider): InertiaResponse|RedirectResponse
    {
        $provider = strtolower($provider);
        if (! in_array($provider, ['google'], true)) {
            abort(404);
        }

        abort_unless($this->sandboxAvailable(), 403, 'Sandbox mode is only available in local or testing environments.');

        $portal = self::PORTAL;

        $users = User::query()
            ->where('role', Role::CLIENT)
            ->orderBy('name')
            ->select('id', 'name', 'email')
            ->limit(20)
            ->get();

        return Inertia::render('Auth/SocialSandbox', [
            'provider' => ucfirst($provider),
            'portal' => $portal,
            'users' => $users,
        ]);
    }

    /**
     * Handle sandbox login submission.
     */
    public function sandboxLogin(Request $request, string $provider): RedirectResponse
    {
        $provider = strtolower($provider);
        if (! in_array($provider, ['google'], true)) {
            abort(404);
        }

        abort_unless($this->sandboxAvailable(), 403, 'Sandbox mode is only available in local or testing environments.');

        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
            'portal' => ['required', 'string'],
        ]);

        $portal = self::PORTAL;
        $email = strtolower(trim($data['email']));
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            $name = explode('@', $email)[0];
        }

        return $this->authenticateAndRedirect($request, $email, $name, $portal);
    }

    /**
     * The sandbox signs in without a password, so it must never run on a live server.
     */
    private function sandboxAvailable(): bool
    {
        return app()->environment('testing') || LoginOtpService::isLocalDev();
    }

    /**
     * Authenticate the user and redirect to portal dashboard.
     */
    private function authenticateAndRedirect(Request $request, string $email, string $name, string $portal): RedirectResponse
    {
        $email = strtolower(trim($email));
        $user = User::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
            ->orderBy('id')
            ->first();

        if (! $user) {
            // A customer may already exist with this email (e.g. created by an admin
            // without a login). Reuse it instead of registering a duplicate customer.
            $customer = Customer::query()
                ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                ->orderBy('id')
                ->first();

            $user = $customer?->users()
                ->where('role', Role::CLIENT)
                ->orderBy('id')
                ->first();
        }

        if (! $user) {
            // Social registration: create the customer (if needed) and its login user
            $customer ??= Customer::create([
                'name' => $name,
                'email' => $email,
                'status' => 'active',
            ]);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::random(24)),
                'role' => Role::CLIENT,
                'customer_id' => $customer->id,
                'currency' => Currency::DEFAULT,
                'status' => 'active',
            ]);
        }

        // Google stands in for the password only. It must not skip an admin's
        // emailed code or let a staff account into a portal it does not belong to.
        if ($this->loginService->validatePortalAccess($portal, $user) !== null) {
            return redirect(Portal::portalLoginUrl($portal))
                ->with('social_error', 'This account cannot use Google login. Please sign in with your password.');
        }

        $guard = Portal::guard($portal);
        Auth::guard($guard)->login($user);

        $request->session()->regenerate();
        $request->session()->forget('impersonator_id');
        Portal::setPortal($request, $portal);

        return redirect()->intended($this->loginService->defaultRedirectUrlFor($portal, $user));
    }
}
