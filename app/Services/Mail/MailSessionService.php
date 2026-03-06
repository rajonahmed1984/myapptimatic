<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Models\MailAccountSession;
use App\Models\SalesRepresentative;
use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class MailSessionService
{
    public const SESSION_TOKEN_KEY = 'mail.session_token';
    public const SESSION_ACCOUNT_KEY = 'mail.active_account_id';
    public const SESSION_SECRET_KEY = 'mail.auth_secret';
    public const COOKIE_TOKEN_KEY = 'mail_session_token';
    public const COOKIE_ACCOUNT_KEY = 'mail_session_account';

    public function __construct(private readonly CookieJar $cookieJar)
    {
    }

    public function createSession(Request $request, array $actor, MailAccount $account, string $password, bool $remember): MailAccountSession
    {
        $token = Str::random(64);
        $encryptedSecret = Crypt::encryptString($password);

        $session = MailAccountSession::query()
            ->where('assignee_type', $actor['type'])
            ->where('assignee_id', $actor['id'])
            ->where('mail_account_id', $account->id)
            ->whereNull('invalidated_at')
            ->orderByDesc('id')
            ->first();

        if (! $session) {
            $session = new MailAccountSession([
                'assignee_type' => $actor['type'],
                'assignee_id' => $actor['id'],
                'mail_account_id' => $account->id,
            ]);
        }

        $session->forceFill([
            'session_token_hash' => hash('sha256', $token),
            'auth_secret' => $encryptedSecret,
            'remember' => $remember,
            'last_validated_at' => now(),
            // Keep mailbox login active until credentials fail or explicit logout.
            'expires_at' => null,
            'invalidated_at' => null,
        ])->save();

        $this->invalidateDuplicateSessionsForActorAccount($actor, $account->id, (int) $session->id);

        $request->session()->put(self::SESSION_TOKEN_KEY, $token);
        $request->session()->put(self::SESSION_ACCOUNT_KEY, $account->id);
        $request->session()->put(self::SESSION_SECRET_KEY, $encryptedSecret);
        $this->queueRememberCookies($token, $account->id);

        return $session;
    }

    public function activateExistingSessionForAccount(Request $request, array $actor, MailAccount $account): bool
    {
        $session = MailAccountSession::query()
            ->where('assignee_type', $actor['type'])
            ->where('assignee_id', $actor['id'])
            ->where('mail_account_id', $account->id)
            ->whereNull('invalidated_at')
            ->whereNotNull('auth_secret')
            ->orderByDesc('last_validated_at')
            ->orderByDesc('id')
            ->first();

        if (! $session) {
            return false;
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            $session->forceFill([
                'invalidated_at' => now(),
            ])->save();

            return false;
        }

        $token = Str::random(64);
        $session->forceFill([
            'session_token_hash' => hash('sha256', $token),
            'last_validated_at' => now(),
        ])->save();

        $this->invalidateDuplicateSessionsForActorAccount($actor, $account->id, (int) $session->id);
        $this->syncLocalState($request, $token, (int) $account->id, (string) ($session->auth_secret ?? ''));

        return true;
    }

    public function validateSession(Request $request): ?MailAccountSession
    {
        $actor = $this->resolveActor($request);
        if (! $actor) {
            return null;
        }

        [$token, $accountId] = $this->resolveTokenAndAccount($request);

        if ($token === '' || $accountId <= 0) {
            return null;
        }

        $session = MailAccountSession::query()
            ->where('assignee_type', $actor['type'])
            ->where('assignee_id', $actor['id'])
            ->where('mail_account_id', $accountId)
            ->where('session_token_hash', hash('sha256', $token))
            ->whereNull('invalidated_at')
            ->with('mailAccount')
            ->first();

        if (! $session) {
            $this->forgetLocalState($request);
            return null;
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            $this->invalidateSession($request, $session);

            return null;
        }

        $this->syncLocalState($request, $token, $accountId, (string) ($session->auth_secret ?? ''));

        return $session;
    }

    public function invalidateCurrent(Request $request): void
    {
        $session = $this->validateSession($request);
        if ($session) {
            $this->invalidateSession($request, $session);
            return;
        }

        $this->forgetLocalState($request);
    }

    public function invalidateSession(Request $request, MailAccountSession $session): void
    {
        $session->forceFill([
            'invalidated_at' => now(),
        ])->save();

        $this->forgetLocalState($request);
    }

    public function invalidateAllForAccount(int $mailAccountId): void
    {
        MailAccountSession::query()
            ->where('mail_account_id', $mailAccountId)
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now()]);
    }

    public function resolveActor(Request $request): ?array
    {
        if (Auth::guard('employee')->check()) {
            $employee = $request->attributes->get('employee') ?: Auth::guard('employee')->user()?->employee;

            if (! $employee) {
                return null;
            }

            return ['type' => 'employee', 'id' => (int) $employee->id, 'user' => Auth::guard('employee')->user()];
        }

        if (Auth::guard('sales')->check()) {
            $salesRep = $request->attributes->get('salesRep');
            if (! $salesRep) {
                $user = Auth::guard('sales')->user();
                $salesRep = $user ? SalesRepresentative::query()->where('user_id', $user->id)->first() : null;
            }

            if (! $salesRep) {
                return null;
            }

            return ['type' => 'sales_rep', 'id' => (int) $salesRep->id, 'user' => Auth::guard('sales')->user()];
        }

        if (Auth::guard('support')->check()) {
            return ['type' => 'support', 'id' => (int) Auth::guard('support')->id(), 'user' => Auth::guard('support')->user()];
        }

        if (Auth::guard('web')->check()) {
            return ['type' => 'user', 'id' => (int) Auth::guard('web')->id(), 'user' => Auth::guard('web')->user()];
        }

        return null;
    }

    public function decryptPassword(Request $request): ?string
    {
        $encrypted = (string) $request->session()->get(self::SESSION_SECRET_KEY, '');

        if ($encrypted === '') {
            $session = $this->validateSession($request);
            $encrypted = (string) ($session?->auth_secret ?? '');
        }

        if ($encrypted === '') {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isFresh(MailAccountSession $session): bool
    {
        $minutes = (int) config('apptimatic_email.validation_interval_minutes', 5);
        $minutes = max($minutes, 1);
        $lastValidatedAt = $session->last_validated_at;

        if (! $lastValidatedAt instanceof Carbon) {
            return false;
        }

        return $lastValidatedAt->gt(now()->subMinutes($minutes));
    }

    public function touchValidated(MailAccountSession $session): void
    {
        $session->forceFill(['last_validated_at' => now()])->save();
    }

    private function forgetLocalState(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_TOKEN_KEY,
            self::SESSION_ACCOUNT_KEY,
            self::SESSION_SECRET_KEY,
        ]);

        $this->forgetRememberCookies();
    }

    private function resolveTokenAndAccount(Request $request): array
    {
        $sessionToken = (string) $request->session()->get(self::SESSION_TOKEN_KEY, '');
        $sessionAccountId = (int) $request->session()->get(self::SESSION_ACCOUNT_KEY, 0);

        if ($sessionToken !== '' && $sessionAccountId > 0) {
            return [$sessionToken, $sessionAccountId];
        }

        $cookieToken = (string) $request->cookie(self::COOKIE_TOKEN_KEY, '');
        $cookieAccountId = (int) $request->cookie(self::COOKIE_ACCOUNT_KEY, 0);

        if ($cookieToken === '' || $cookieAccountId <= 0) {
            return ['', 0];
        }

        return [$cookieToken, $cookieAccountId];
    }

    private function syncLocalState(Request $request, string $token, int $accountId, string $encryptedSecret): void
    {
        $request->session()->put(self::SESSION_TOKEN_KEY, $token);
        $request->session()->put(self::SESSION_ACCOUNT_KEY, $accountId);

        if ($encryptedSecret !== '') {
            $request->session()->put(self::SESSION_SECRET_KEY, $encryptedSecret);
        }

        $this->queueRememberCookies($token, $accountId);
    }

    private function invalidateDuplicateSessionsForActorAccount(array $actor, int $accountId, int $activeSessionId): void
    {
        MailAccountSession::query()
            ->where('assignee_type', $actor['type'])
            ->where('assignee_id', $actor['id'])
            ->where('mail_account_id', $accountId)
            ->whereNull('invalidated_at')
            ->where('id', '!=', $activeSessionId)
            ->update(['invalidated_at' => now()]);
    }

    private function queueRememberCookies(string $token, int $accountId): void
    {
        $days = (int) config('apptimatic_email.persistent_login_days', 3650);
        $days = max($days, 1);
        $minutes = $days * 24 * 60;

        $path = (string) config('session.path', '/');
        $domain = config('session.domain');
        $secure = (bool) config('session.secure', false);
        $sameSite = (string) config('session.same_site', 'lax');

        $this->cookieJar->queue(cookie(self::COOKIE_TOKEN_KEY, $token, $minutes, $path, $domain, $secure, true, false, $sameSite));
        $this->cookieJar->queue(cookie(self::COOKIE_ACCOUNT_KEY, (string) $accountId, $minutes, $path, $domain, $secure, true, false, $sameSite));
    }

    private function forgetRememberCookies(): void
    {
        $path = (string) config('session.path', '/');
        $domain = config('session.domain');

        $this->cookieJar->queue($this->cookieJar->forget(self::COOKIE_TOKEN_KEY, $path, $domain));
        $this->cookieJar->queue($this->cookieJar->forget(self::COOKIE_ACCOUNT_KEY, $path, $domain));
    }
}
