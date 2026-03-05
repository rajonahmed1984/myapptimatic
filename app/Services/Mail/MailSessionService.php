<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use App\Models\MailAccountSession;
use App\Models\SalesRepresentative;
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

    public function createSession(Request $request, array $actor, MailAccount $account, string $password, bool $remember): MailAccountSession
    {
        $this->invalidateAllForActor($actor);

        $token = Str::random(64);

        $session = MailAccountSession::create([
            'assignee_type' => $actor['type'],
            'assignee_id' => $actor['id'],
            'mail_account_id' => $account->id,
            'session_token_hash' => hash('sha256', $token),
            'remember' => $remember,
            'last_validated_at' => now(),
            'expires_at' => $remember ? now()->addDays((int) config('apptimatic_email.remember_days', 30)) : null,
        ]);

        $request->session()->put(self::SESSION_TOKEN_KEY, $token);
        $request->session()->put(self::SESSION_ACCOUNT_KEY, $account->id);
        $request->session()->put(self::SESSION_SECRET_KEY, Crypt::encryptString($password));

        return $session;
    }

    public function validateSession(Request $request): ?MailAccountSession
    {
        $actor = $this->resolveActor($request);
        if (! $actor) {
            return null;
        }

        $token = (string) $request->session()->get(self::SESSION_TOKEN_KEY, '');
        $accountId = (int) $request->session()->get(self::SESSION_ACCOUNT_KEY, 0);

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
            return null;
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            $this->invalidateSession($request, $session);

            return null;
        }

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

    private function invalidateAllForActor(array $actor): void
    {
        MailAccountSession::query()
            ->where('assignee_type', $actor['type'])
            ->where('assignee_id', $actor['id'])
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now()]);
    }

    private function forgetLocalState(Request $request): void
    {
        $request->session()->forget([
            self::SESSION_TOKEN_KEY,
            self::SESSION_ACCOUNT_KEY,
            self::SESSION_SECRET_KEY,
        ]);
    }
}
