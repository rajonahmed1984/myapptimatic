<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Services\ApptimaticEmailStubRepository;
use App\Services\Mail\ImapInboxService;
use App\Services\Mail\MailSessionService;
use App\Support\DateTimeFormat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MailInboxController extends Controller
{
    public function __construct(
        private readonly MailSessionService $mailSessionService,
        private readonly ImapInboxService $imapInboxService
    )
    {
    }

    public function index(Request $request, ApptimaticEmailStubRepository $mailbox): InertiaResponse
    {
        $historyEmail = $this->selectedHistoryEmail($request);
        $selectedMessageId = $this->selectedViewMessageId($request);
        [$messages, $selectedMessage, $threadMessages, $syncMeta, $historyEmailOptions, $mailboxUnreadCount] = $this->resolveMailboxData(
            $request,
            $mailbox,
            $selectedMessageId,
            $historyEmail,
            false
        );

        return Inertia::render('Admin/ApptimaticEmail/Inbox', $this->inboxInertiaProps(
            $request,
            $messages,
            $selectedMessage,
            $threadMessages,
            $syncMeta,
            $historyEmail,
            $historyEmailOptions,
            $mailboxUnreadCount
        ));
    }

    public function show(Request $request, string $message, ApptimaticEmailStubRepository $mailbox): InertiaResponse
    {
        $historyEmail = $this->selectedHistoryEmail($request);
        [$messages, $selectedMessage, $threadMessages, $syncMeta, $historyEmailOptions, $mailboxUnreadCount] = $this->resolveMailboxData(
            $request,
            $mailbox,
            $message,
            $historyEmail,
            true
        );

        return Inertia::render('Admin/ApptimaticEmail/Inbox', $this->inboxInertiaProps(
            $request,
            $messages,
            $selectedMessage,
            $threadMessages,
            $syncMeta,
            $historyEmail,
            $historyEmailOptions,
            $mailboxUnreadCount
        ));
    }

    public function reply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'message_id' => ['required', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:1000'],
            'cc' => ['nullable', 'string', 'max:1000'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ]);

        $session = $this->mailSessionService->validateSession($request);
        $password = $this->mailSessionService->decryptPassword($request);
        $mailAccount = $session?->mailAccount;

        if (! $this->canUseLiveMailbox($mailAccount, $password)) {
            return back()->withErrors(['reply' => 'Mailbox session expired. Please login again.']);
        }

        $to = $this->parseAddressList((string) $data['to']);
        $cc = $this->parseAddressList((string) ($data['cc'] ?? ''));

        if ($to === []) {
            return back()->withErrors(['reply' => 'Please provide at least one valid recipient email.']);
        }

        $smtp = $this->smtpSettingsForReply($mailAccount);
        if ($smtp['host'] === '' || $smtp['port'] <= 0) {
            return back()->withErrors(['reply' => 'SMTP host/port is missing. Set APPTIMATIC_EMAIL_SMTP_* in .env.']);
        }

        try {
            $scheme = $smtp['encryption'] === 'ssl' ? 'smtps' : 'smtp';
            $query = [];
            if ($smtp['encryption'] === 'tls') {
                $query['encryption'] = 'tls';
            }
            if (! $smtp['validate_cert']) {
                $query['verify_peer'] = 0;
                $query['verify_peer_name'] = 0;
                $query['allow_self_signed'] = 1;
            }

            $dsn = sprintf(
                '%s://%s:%s@%s:%d%s',
                $scheme,
                rawurlencode((string) $mailAccount->email),
                rawurlencode((string) $password),
                $smtp['host'],
                $smtp['port'],
                $query === [] ? '' : ('?' . http_build_query($query))
            );

            $mailer = new Mailer(Transport::fromDsn($dsn));
            $email = (new Email())
                ->from(new Address((string) $mailAccount->email, (string) ($mailAccount->display_name ?: $mailAccount->email)))
                ->replyTo((string) $mailAccount->email)
                ->subject((string) $data['subject'])
                ->text((string) $data['body']);

            foreach ($to as $address) {
                $email->addTo($address);
            }

            foreach ($cc as $address) {
                $email->addCc($address);
            }

            $mailer->send($email);

            return back()->with('status', 'Reply sent successfully.');
        } catch (\Throwable $exception) {
            return back()->withErrors([
                'reply' => 'Reply send failed: ' . $this->shortError($exception->getMessage()),
            ]);
        }
    }

    public function stream(Request $request, ApptimaticEmailStubRepository $stub): StreamedResponse
    {
        $routeName = (string) $request->route()?->getName();
        $loginRoute = $this->resolveRoute($routeName, 'login');
        $pollSeconds = max((int) config('apptimatic_email.sse_poll_seconds', 5), 2);
        $maxRuntimeSeconds = max((int) config('apptimatic_email.sse_max_runtime_seconds', 55), 15);

        return response()->stream(function () use ($request, $stub, $pollSeconds, $maxRuntimeSeconds, $loginRoute): void {
            @set_time_limit(0);
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $startedAt = microtime(true);
            $lastHash = null;

            while (! connection_aborted() && (microtime(true) - $startedAt) < $maxRuntimeSeconds) {
                $snapshot = $this->resolveMailboxSnapshot($request, $stub);
                $nowIso = now()->toIso8601String();

                if (! ($snapshot['ok'] ?? false)) {
                    $this->emitSse('mail.expired', [
                        'message' => 'Mailbox session expired.',
                        'login' => route($loginRoute),
                        'at' => $nowIso,
                    ]);

                    return;
                }

                $hash = (string) ($snapshot['hash'] ?? '');
                $mode = (string) ($snapshot['mode'] ?? 'stub');

                if ($lastHash === null) {
                    $lastHash = $hash;
                    $this->emitSse('mail.connected', [
                        'mode' => $mode,
                        'hash' => $hash,
                        'at' => $nowIso,
                    ]);
                } elseif ($hash !== '' && $hash !== $lastHash) {
                    $lastHash = $hash;
                    $this->emitSse('mail.updated', [
                        'mode' => $mode,
                        'hash' => $hash,
                        'at' => $nowIso,
                    ]);
                } else {
                    $this->emitSse('mail.ping', [
                        'mode' => $mode,
                        'at' => $nowIso,
                    ]);
                }

                sleep($pollSeconds);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function inboxInertiaProps(
        Request $request,
        array $messages,
        ?array $selectedMessage,
        array $threadMessages,
        array $syncMeta,
        string $historyEmail,
        array $historyEmailOptions,
        int $mailboxUnreadCount
    ): array {
        $routeName = (string) $request->route()?->getName();
        $inboxRoute = $this->resolveRoute($routeName, 'inbox');
        $showRoute = $this->resolveRoute($routeName, 'show');
        $logoutRoute = $this->resolveRoute($routeName, 'logout');
        $loginRoute = $this->resolveRoute($routeName, 'login');
        $streamRoute = $this->resolveRoute($routeName, 'stream');
        $replyRoute = $this->resolveRoute($routeName, 'reply');
        $selectedMessageId = (string) ($selectedMessage['id'] ?? '');

        return [
            'pageTitle' => 'Apptimatic Email',
            'unread_count' => $mailboxUnreadCount,
            'portal_label' => $this->portalLabelFromRoute($routeName),
            'profile_name' => (string) ($request->user()?->name ?? 'User'),
            'profile_avatar_path' => $request->user()?->avatar_path,
            'routes' => [
                'inbox' => route($inboxRoute),
                'logout' => route($logoutRoute),
                'login' => route($loginRoute),
                'stream' => route($streamRoute),
                'reply' => route($replyRoute),
                'manage' => $this->resolveManageRoute($routeName),
            ],
            'history_email_filter' => [
                'enabled' => $this->canFilterHistoryEmail($request),
                'selected' => $historyEmail,
                'options' => $historyEmailOptions,
            ],
            'mailbox_switch' => $this->mailboxSwitchData($request),
            'messages' => collect($messages)->map(function (array $message) use ($selectedMessageId, $showRoute, $historyEmail) {
                $id = (string) ($message['id'] ?? '');
                $params = ['message' => $id];
                if ($historyEmail !== '') {
                    $params['history_email'] = $historyEmail;
                }

                return [
                    'id' => $id,
                    'sender_name' => (string) ($message['sender_name'] ?? 'Unknown sender'),
                    'sender_email' => (string) ($message['sender_email'] ?? ''),
                    'to' => (string) ($message['to'] ?? ''),
                    'subject' => (string) ($message['subject'] ?? '(No subject)'),
                    'snippet' => (string) ($message['snippet'] ?? ''),
                    'unread' => (bool) ($message['unread'] ?? false),
                    'is_selected' => $selectedMessageId !== '' && $selectedMessageId === $id,
                    'received_at_display' => DateTimeFormat::formatDateTime($message['received_at'] ?? null, ''),
                    'routes' => [
                        'show' => route($showRoute, $params),
                    ],
                ];
            })->values()->all(),
            'selected_message' => $selectedMessage ? [
                'id' => (string) ($selectedMessage['id'] ?? ''),
                'sender_name' => (string) ($selectedMessage['sender_name'] ?? 'Unknown sender'),
                'sender_email' => (string) ($selectedMessage['sender_email'] ?? ''),
                'to' => (string) ($selectedMessage['to'] ?? ''),
                'subject' => (string) ($selectedMessage['subject'] ?? '(No subject)'),
                'received_at_display' => DateTimeFormat::formatDateTime($selectedMessage['received_at'] ?? null, ''),
                'thread_count' => count($threadMessages),
            ] : null,
            'thread_messages' => collect($threadMessages)->map(function (array $threadMessage) use ($selectedMessageId) {
                return [
                    'id' => (string) ($threadMessage['id'] ?? ''),
                    'sender_name' => (string) ($threadMessage['sender_name'] ?? 'Unknown sender'),
                    'sender_email' => (string) ($threadMessage['sender_email'] ?? ''),
                    'to' => (string) ($threadMessage['to'] ?? ''),
                    'subject' => (string) ($threadMessage['subject'] ?? '(No subject)'),
                    'body' => (string) ($threadMessage['body'] ?? ''),
                    'received_at_display' => DateTimeFormat::formatDateTime($threadMessage['received_at'] ?? null, ''),
                    'is_selected' => $selectedMessageId !== '' && $selectedMessageId === (string) ($threadMessage['id'] ?? ''),
                ];
            })->values()->all(),
            'sync_meta' => $syncMeta,
        ];
    }

    private function resolveMailboxData(
        Request $request,
        ApptimaticEmailStubRepository $stub,
        ?string $selectedMessageId = null,
        string $historyEmail = '',
        bool $strictSelection = false
    ): array {
        $session = $this->mailSessionService->validateSession($request);
        $password = $this->mailSessionService->decryptPassword($request);
        $mailAccount = $session?->mailAccount;

        if ($this->canUseLiveMailbox($mailAccount, $password)) {
            $allMessages = $this->imapInboxService->inbox($mailAccount, $password, 80);
            $historyEmailOptions = $this->historyEmailOptions($allMessages);
            $messages = $this->filterMessagesByHistoryEmail($allMessages, $historyEmail);
            $mailboxUnreadCount = (int) collect($allMessages)->where('unread', true)->count();

            $selectedMessage = $selectedMessageId
                ? collect($messages)->first(fn (array $message) => (string) ($message['id'] ?? '') === (string) $selectedMessageId)
                : null;

            if ($strictSelection && $selectedMessageId !== null && ! $selectedMessage) {
                abort(404);
            }

            $threadMessages = [];
            if ($selectedMessage) {
                $threadId = (string) ($selectedMessage['thread_id'] ?? '');

                $threadMessages = array_values(array_filter($messages, function (array $message) use ($threadId): bool {
                    return (string) ($message['thread_id'] ?? '') === $threadId;
                }));

                usort($threadMessages, function (array $a, array $b): int {
                    return ($a['received_at']?->getTimestamp() ?? 0) <=> ($b['received_at']?->getTimestamp() ?? 0);
                });
            }

            return [$messages, $selectedMessage, $threadMessages, $this->syncMeta('live'), $historyEmailOptions, $mailboxUnreadCount];
        }

        $allMessages = $stub->inbox();
        $historyEmailOptions = $this->historyEmailOptions($allMessages);
        $messages = $this->filterMessagesByHistoryEmail($allMessages, $historyEmail);
        $mailboxUnreadCount = (int) collect($allMessages)->where('unread', true)->count();
        $selectedMessage = $selectedMessageId
            ? collect($messages)->first(fn (array $message) => (string) ($message['id'] ?? '') === (string) $selectedMessageId)
            : null;

        if ($strictSelection && $selectedMessageId !== null && ! $selectedMessage) {
            abort(404);
        }

        $threadMessages = $selectedMessage
            ? array_values(array_filter($messages, function (array $message) use ($selectedMessage): bool {
                return (string) ($message['thread_id'] ?? '') === (string) ($selectedMessage['thread_id'] ?? '');
            }))
            : [];

        usort($threadMessages, function (array $a, array $b): int {
            return ($a['received_at']?->getTimestamp() ?? 0) <=> ($b['received_at']?->getTimestamp() ?? 0);
        });

        return [$messages, $selectedMessage, $threadMessages, $this->syncMeta('stub'), $historyEmailOptions, $mailboxUnreadCount];
    }

    private function syncMeta(string $mode): array
    {
        $seconds = (int) config('apptimatic_email.inbox_refresh_seconds', 60);
        $seconds = max($seconds, 15);

        return [
            'mode' => $mode,
            'interval_seconds' => $seconds,
            'last_synced_at' => now()->toIso8601String(),
        ];
    }

    private function resolveMailboxSnapshot(Request $request, ApptimaticEmailStubRepository $stub): array
    {
        $session = $this->mailSessionService->validateSession($request);
        if (! $session) {
            return ['ok' => false, 'mode' => 'none', 'hash' => ''];
        }

        $password = $this->mailSessionService->decryptPassword($request);
        $mailAccount = $session->mailAccount;

        if ($this->canUseLiveMailbox($mailAccount, $password)) {
            $hash = $this->imapInboxService->snapshotHash($mailAccount, $password, 40);

            return [
                'ok' => true,
                'mode' => 'live',
                'hash' => $hash !== '' ? $hash : 'live-unavailable',
            ];
        }

        $stubMessages = $stub->inbox();
        $stablePayload = array_map(static function (array $message): array {
            return [
                'id' => (string) ($message['id'] ?? ''),
                'subject' => (string) ($message['subject'] ?? ''),
                'unread' => (bool) ($message['unread'] ?? false),
            ];
        }, $stubMessages);

        return [
            'ok' => true,
            'mode' => 'stub',
            'hash' => 'stub-' . hash('sha256', json_encode($stablePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
        ];
    }

    private function emitSse(string $event, array $payload): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }

    private function resolveRoute(string $routeName, string $action): string
    {
        if (str_starts_with($routeName, 'employee.')) {
            return 'employee.apptimatic-email.' . $action;
        }

        if (str_starts_with($routeName, 'rep.')) {
            return 'rep.apptimatic-email.' . $action;
        }

        if (str_starts_with($routeName, 'support.')) {
            return 'support.apptimatic-email.' . $action;
        }

        return 'admin.apptimatic-email.' . $action;
    }

    private function portalLabelFromRoute(string $routeName): string
    {
        if (str_starts_with($routeName, 'employee.')) {
            return 'Employee portal';
        }

        if (str_starts_with($routeName, 'rep.')) {
            return 'Sales portal';
        }

        if (str_starts_with($routeName, 'support.')) {
            return 'Support portal';
        }

        return 'Admin portal';
    }

    private function resolveManageRoute(string $routeName): ?string
    {
        if (! str_starts_with($routeName, 'admin.')) {
            return null;
        }

        if (! \Illuminate\Support\Facades\Route::has('admin.apptimatic-email.manage')) {
            return null;
        }

        return route('admin.apptimatic-email.manage');
    }

    private function canUseLiveMailbox(mixed $mailAccount, mixed $password): bool
    {
        return $mailAccount
            && is_string($password)
            && $password !== ''
            && $this->imapInboxService->isAvailable();
    }

    private function mailboxSwitchData(Request $request): array
    {
        $user = $request->user();
        $isMasterAdmin = (bool) ($user && method_exists($user, 'isMasterAdmin') && $user->isMasterAdmin());
        if (! $isMasterAdmin) {
            return [
                'enabled' => false,
                'current_email' => '',
                'options' => [],
            ];
        }

        $actor = $this->mailSessionService->resolveActor($request);
        if (! is_array($actor)) {
            return [
                'enabled' => false,
                'current_email' => '',
                'options' => [],
            ];
        }

        $options = $this->availableMailboxOptions($actor, $user);
        $session = $this->mailSessionService->validateSession($request);
        $currentEmail = strtolower(trim((string) ($session?->mailAccount?->email ?? '')));

        return [
            'enabled' => count($options) > 1,
            'current_email' => $currentEmail,
            'options' => $options,
        ];
    }

    private function availableMailboxOptions(array $actor, mixed $user): array
    {
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin() && (bool) config('apptimatic_email.allow_admin_global_mailboxes', false)) {
            $accounts = MailAccount::query()
                ->orderBy('email')
                ->get(['id', 'email', 'display_name']);
        } else {
            $assigneeTypes = $this->candidateAssigneeTypes($actor, $user);
            $mailboxIds = MailAccountAssignment::query()
                ->whereIn('assignee_type', $assigneeTypes)
                ->where('assignee_id', (int) ($actor['id'] ?? 0))
                ->where('can_read', true)
                ->pluck('mail_account_id');

            $accounts = MailAccount::query()
                ->whereIn('id', $mailboxIds)
                ->orderBy('email')
                ->get(['id', 'email', 'display_name']);
        }

        return $accounts->map(function (MailAccount $mailAccount): array {
            $email = strtolower((string) ($mailAccount->email ?? ''));
            $displayName = trim((string) ($mailAccount->display_name ?? ''));

            return [
                'id' => (int) $mailAccount->id,
                'email' => $email,
                'label' => $displayName !== '' ? $displayName . ' (' . $email . ')' : $email,
            ];
        })->values()->all();
    }

    private function candidateAssigneeTypes(array $actor, mixed $user): array
    {
        $types = [strtolower((string) ($actor['type'] ?? ''))];

        if ($user && method_exists($user, 'isSupport') && $user->isSupport()) {
            $types[] = 'support';
            $types[] = 'user';
        }

        return array_values(array_unique(array_filter($types)));
    }

    private function canFilterHistoryEmail(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user && method_exists($user, 'isMasterAdmin') && $user->isMasterAdmin());
    }

    private function selectedHistoryEmail(Request $request): string
    {
        if (! $this->canFilterHistoryEmail($request)) {
            return '';
        }

        $email = strtolower(trim((string) $request->query('history_email', '')));
        if ($email === '' || mb_strlen($email) > 255 || ! str_contains($email, '@')) {
            return '';
        }

        return $email;
    }

    private function historyEmailOptions(array $messages): array
    {
        $options = array_values(array_filter(array_map(function (array $message): string {
            return strtolower(trim((string) ($message['sender_email'] ?? '')));
        }, $messages)));

        $options = array_values(array_unique($options));
        sort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    private function filterMessagesByHistoryEmail(array $messages, string $historyEmail): array
    {
        if ($historyEmail === '') {
            return $messages;
        }

        return array_values(array_filter($messages, function (array $message) use ($historyEmail): bool {
            $senderEmail = strtolower(trim((string) ($message['sender_email'] ?? '')));
            $toEmail = strtolower(trim((string) ($message['to'] ?? '')));

            return $senderEmail === $historyEmail || $toEmail === $historyEmail;
        }));
    }

    private function selectedViewMessageId(Request $request): ?string
    {
        $value = trim((string) $request->query('view', ''));
        if ($value === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9\-]+$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function parseAddressList(string $value): array
    {
        $parts = preg_split('/[,;]+/', $value) ?: [];
        $addresses = [];

        foreach ($parts as $part) {
            $candidate = strtolower(trim((string) $part));
            if ($candidate === '') {
                continue;
            }

            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $addresses[] = $candidate;
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * @return array{host: string, port: int, encryption: string, validate_cert: bool}
     */
    private function smtpSettingsForReply(mixed $mailAccount): array
    {
        $imapHost = trim((string) ($mailAccount?->imap_host ?: config('apptimatic_email.imap.host', '')));
        $imapEncryption = strtolower((string) ($mailAccount?->imap_encryption ?: config('apptimatic_email.imap.encryption', 'ssl')));

        $derivedHost = $this->derivedSmtpHost($imapHost);
        $derivedEncryption = in_array($imapEncryption, ['ssl', 'tls', 'none'], true) ? $imapEncryption : 'tls';

        $host = trim((string) config('apptimatic_email.smtp.host', $derivedHost));
        $encryption = strtolower((string) config('apptimatic_email.smtp.encryption', $derivedEncryption));
        if (! in_array($encryption, ['ssl', 'tls', 'none'], true)) {
            $encryption = $derivedEncryption;
        }

        $defaultPort = match ($encryption) {
            'ssl' => 465,
            'tls' => 587,
            default => 25,
        };

        $port = (int) config('apptimatic_email.smtp.port', $defaultPort);
        if ($port <= 0) {
            $port = $defaultPort;
        }

        return [
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'validate_cert' => (bool) config('apptimatic_email.smtp.validate_cert', true),
        ];
    }

    private function derivedSmtpHost(string $imapHost): string
    {
        if ($imapHost === '') {
            return '';
        }

        if (str_starts_with(strtolower($imapHost), 'imap.')) {
            return 'smtp.' . substr($imapHost, 5);
        }

        return $imapHost;
    }

    private function shortError(string $message): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $message) ?: $message);
        if ($value === '') {
            return 'Unknown error';
        }

        if (mb_strlen($value) > 180) {
            return mb_substr($value, 0, 177) . '...';
        }

        return $value;
    }
}
