<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Http\Requests\MailLoginRequest;
use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Services\Mail\ImapAuthService;
use App\Services\Mail\MailSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MailLoginController extends Controller
{
    public function __construct(
        private readonly MailSessionService $mailSessionService,
        private readonly ImapAuthService $imapAuthService
    ) {
    }

    public function showLogin(Request $request): InertiaResponse|RedirectResponse
    {
        $routeName = (string) $request->route()?->getName();
        $inboxRoute = $this->resolveInboxRoute($routeName);
        $switchRequested = (bool) $request->boolean('switch', false);
        $actor = $this->mailSessionService->resolveActor($request);
        abort_if(! $actor, 403);

        if (! $switchRequested && $this->mailSessionService->validateSession($request)) {
            return redirect()->route($inboxRoute);
        }

        $mailboxes = $this->availableMailboxes($actor, $actor['user']);
        $prefillEmail = strtolower(trim((string) $request->query('email', '')));
        $prefillAllowed = collect($mailboxes)->contains(function (array $mailbox) use ($prefillEmail): bool {
            return strtolower((string) ($mailbox['email'] ?? '')) === $prefillEmail;
        });
        if (! $prefillAllowed) {
            $prefillEmail = '';
        }

        if ($switchRequested && $prefillEmail !== '') {
            $selectedMailboxId = (int) (collect($mailboxes)->first(
                fn (array $mailbox): bool => strtolower((string) ($mailbox['email'] ?? '')) === $prefillEmail
            )['id'] ?? 0);

            if ($selectedMailboxId > 0) {
                $mailAccount = MailAccount::query()->whereKey($selectedMailboxId)->first();
                if ($mailAccount && $this->mailSessionService->activateExistingSessionForAccount($request, $actor, $mailAccount)) {
                    return redirect()->route($inboxRoute);
                }
            }
        }

        return Inertia::render('Mail/Login', [
            'pageTitle' => 'Email Login',
            'mailboxes' => $mailboxes,
            'prefill_email' => $prefillEmail,
            'routes' => [
                'login' => route($this->resolveLoginRoute($routeName)),
                'inbox' => route($inboxRoute),
            ],
            'portal' => $this->portalLabelFromRoute($routeName),
        ]);
    }

    public function login(MailLoginRequest $request): RedirectResponse
    {
        $routeName = (string) $request->route()?->getName();
        $inboxRoute = $this->resolveInboxRoute($routeName);

        $actor = $this->mailSessionService->resolveActor($request);
        abort_if(! $actor, 403);

        $email = strtolower((string) $request->string('email'));
        $password = (string) $request->input('password', '');
        $remember = (bool) $request->boolean('remember', false);

        $mailAccount = MailAccount::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $mailAccount) {
            return back()->withErrors(['email' => 'Invalid email or password'])->withInput();
        }

        if (! Gate::allows('use', [$mailAccount, $actor['type'], (int) $actor['id']])) {
            if (config('app.login_trace')) {
                Log::warning('[MAIL_LOGIN_DENIED]', [
                    'route' => $routeName,
                    'actor_type' => $actor['type'] ?? null,
                    'actor_id' => $actor['id'] ?? null,
                    'mail_account_id' => $mailAccount->id,
                    'mail_account_email' => $mailAccount->email,
                ]);
            }

            return back()->withErrors(['email' => 'This mailbox is not assigned to your account.'])->withInput();
        }

        if (! $this->imapAuthService->verifyCredentials($mailAccount, $password)) {
            $failureType = $this->imapAuthService->lastFailureType();
            $failureDetail = $this->imapAuthService->lastFailureDetail();

            if (config('app.login_trace')) {
                Log::warning('[MAIL_IMAP_AUTH_FAILED]', [
                    'route' => $routeName,
                    'actor_type' => $actor['type'] ?? null,
                    'actor_id' => $actor['id'] ?? null,
                    'mail_account_id' => $mailAccount->id,
                    'mail_account_email' => $mailAccount->email,
                    'failure_type' => $failureType,
                    'failure_detail' => $failureDetail,
                ]);
            }

            $mailAccount->forceFill([
                'status' => 'auth_failed',
                'last_auth_failed_at' => now(),
            ])->save();

            if ($failureType === 'server_unavailable') {
                $message = 'Email server unavailable. Check IMAP host/port/encryption/certificate settings.';
                $formattedDetail = $this->formatImapFailureDetail($failureDetail);
                if ($formattedDetail !== null) {
                    $message .= ' Details: ' . $formattedDetail;
                }

                return back()->withErrors(['email' => $message])->withInput();
            }

            return back()->withErrors(['email' => 'Invalid email or password'])->withInput();
        }

        $mailAccount->forceFill([
            'status' => 'active',
            'last_auth_failed_at' => null,
        ])->save();

        $this->mailSessionService->createSession($request, $actor, $mailAccount, $password, $remember);

        return redirect()->route($inboxRoute);
    }

    public function logout(Request $request): RedirectResponse
    {
        $routeName = (string) $request->route()?->getName();
        $loginRoute = $this->resolveLoginRoute($routeName);

        $this->mailSessionService->invalidateCurrent($request);

        return redirect()->route($loginRoute)->with('status', 'Logged out from email.');
    }

    private function availableMailboxes(array $actor, mixed $user): array
    {
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin() && (bool) config('apptimatic_email.allow_admin_global_mailboxes', false)) {
            return MailAccount::query()
                ->orderBy('email')
                ->get(['id', 'email', 'display_name'])
                ->map(fn (MailAccount $mailAccount) => [
                    'id' => $mailAccount->id,
                    'email' => $mailAccount->email,
                    'display_name' => $mailAccount->display_name,
                ])
                ->all();
        }

        $assigneeTypes = $this->candidateAssigneeTypes($actor, $user);

        $ids = MailAccountAssignment::query()
            ->whereIn('assignee_type', $assigneeTypes)
            ->where('assignee_id', $actor['id'])
            ->where('can_read', true)
            ->pluck('mail_account_id');

        return MailAccount::query()
            ->whereIn('id', $ids)
            ->orderBy('email')
            ->get(['id', 'email', 'display_name'])
            ->map(fn (MailAccount $mailAccount) => [
                'id' => $mailAccount->id,
                'email' => $mailAccount->email,
                'display_name' => $mailAccount->display_name,
            ])
            ->all();
    }

    private function resolveLoginRoute(string $routeName): string
    {
        if (str_starts_with($routeName, 'employee.')) {
            return 'employee.apptimatic-email.login';
        }

        if (str_starts_with($routeName, 'rep.')) {
            return 'rep.apptimatic-email.login';
        }

        if (str_starts_with($routeName, 'support.')) {
            return 'support.apptimatic-email.login';
        }

        return 'admin.apptimatic-email.login';
    }

    private function resolveInboxRoute(string $routeName): string
    {
        if (str_starts_with($routeName, 'employee.')) {
            return 'employee.apptimatic-email.inbox';
        }

        if (str_starts_with($routeName, 'rep.')) {
            return 'rep.apptimatic-email.inbox';
        }

        if (str_starts_with($routeName, 'support.')) {
            return 'support.apptimatic-email.inbox';
        }

        return 'admin.apptimatic-email.inbox';
    }

    private function portalLabelFromRoute(string $routeName): string
    {
        if (str_starts_with($routeName, 'employee.')) {
            return 'Employee Portal';
        }

        if (str_starts_with($routeName, 'rep.')) {
            return 'Sales Portal';
        }

        if (str_starts_with($routeName, 'support.')) {
            return 'Support Portal';
        }

        return 'Admin Portal';
    }

    /**
     * Support users can authenticate via admin (web guard) and support portal (support guard).
     * To prevent assignment mismatches, consider both assignment types.
     */
    private function candidateAssigneeTypes(array $actor, mixed $user): array
    {
        $types = [strtolower((string) ($actor['type'] ?? ''))];

        if ($user && method_exists($user, 'isSupport') && $user->isSupport()) {
            $types[] = 'support';
            $types[] = 'user';
        }

        return array_values(array_unique(array_filter($types)));
    }

    private function formatImapFailureDetail(?string $detail): ?string
    {
        $value = trim((string) $detail);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        if (mb_strlen($value) > 180) {
            $value = mb_substr($value, 0, 177) . '...';
        }

        return $value;
    }
}
