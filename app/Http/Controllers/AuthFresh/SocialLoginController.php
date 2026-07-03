<?php

namespace App\Http\Controllers\AuthFresh;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Enums\Role;
use App\Support\Currency;
use App\Support\AuthFresh\Portal;
use App\Services\AuthFresh\LoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SocialLoginController extends Controller
{
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
        $request->session()->put('social_auth_portal', $portal);
        $request->session()->put('social_auth_provider', $provider);

        $config = config("services.{$provider}");

        if (empty($config['client_id'])) {
            if (app()->environment(['local', 'testing', 'development', 'dev', 'staging']) || config('app.debug')) {
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
        $portal = $request->session()->pull('social_auth_portal', 'web');
        $sessionState = $request->session()->pull("oauth_state_{$provider}");

        $state = $request->input('state');
        if (empty($state) || $state !== $sessionState) {
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
            return redirect(Portal::portalLoginUrl($portal))
                ->with('social_error', 'An error occurred during authentication: ' . $e->getMessage());
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

        if (! (app()->environment(['local', 'testing', 'development', 'dev', 'staging']) || config('app.debug'))) {
            abort(403, 'Sandbox mode is only available in local or testing environments.');
        }

        $portal = Portal::normalize($request->query('portal', 'web'));

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

        if (! (app()->environment(['local', 'testing', 'development', 'dev', 'staging']) || config('app.debug'))) {
            abort(403, 'Sandbox mode is only available in local or testing environments.');
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:255'],
            'portal' => ['required', 'string'],
        ]);

        $portal = Portal::normalize($data['portal']);
        $email = strtolower(trim($data['email']));
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            $name = explode('@', $email)[0];
        }

        return $this->authenticateAndRedirect($request, $email, $name, $portal);
    }

    /**
     * Authenticate the user and redirect to portal dashboard.
     */
    private function authenticateAndRedirect(Request $request, string $email, string $name, string $portal): RedirectResponse
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();

        if (! $user) {
            // Social registration: create customer and user
            $customer = Customer::create([
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

        $guard = Portal::guard($portal);
        Auth::guard($guard)->login($user);

        $request->session()->regenerate();
        Portal::setPortal($request, $portal);

        return redirect()->intended($this->loginService->defaultRedirectUrlFor($portal, $user));
    }
}
