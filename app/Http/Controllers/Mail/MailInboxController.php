<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Services\ApptimaticEmailStubRepository;
use App\Services\Mail\ImapInboxService;
use App\Services\Mail\MailSessionService;
use App\Support\DateTimeFormat;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
        [$messages, $selectedMessage, $threadMessages, $syncMeta] = $this->resolveMailboxData($request, $mailbox);

        return Inertia::render('Admin/ApptimaticEmail/Inbox', $this->inboxInertiaProps($request, $messages, $selectedMessage, $threadMessages, $syncMeta));
    }

    public function show(Request $request, string $message, ApptimaticEmailStubRepository $mailbox): InertiaResponse
    {
        [$messages, $selectedMessage, $threadMessages, $syncMeta] = $this->resolveMailboxData($request, $mailbox, $message);

        return Inertia::render('Admin/ApptimaticEmail/Inbox', $this->inboxInertiaProps($request, $messages, $selectedMessage, $threadMessages, $syncMeta));
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
        array $syncMeta
    ): array {
        $routeName = (string) $request->route()?->getName();
        $inboxRoute = $this->resolveRoute($routeName, 'inbox');
        $showRoute = $this->resolveRoute($routeName, 'show');
        $logoutRoute = $this->resolveRoute($routeName, 'logout');
        $loginRoute = $this->resolveRoute($routeName, 'login');
        $streamRoute = $this->resolveRoute($routeName, 'stream');

        $selectedMessageId = (string) ($selectedMessage['id'] ?? '');

        return [
            'pageTitle' => 'Apptimatic Email',
            'unread_count' => (int) collect($messages)->where('unread', true)->count(),
            'portal_label' => $this->portalLabelFromRoute($routeName),
            'profile_name' => (string) ($request->user()?->name ?? 'User'),
            'profile_avatar_path' => $request->user()?->avatar_path,
            'routes' => [
                'inbox' => route($inboxRoute),
                'logout' => route($logoutRoute),
                'login' => route($loginRoute),
                'stream' => route($streamRoute),
                'manage' => $this->resolveManageRoute($routeName),
            ],
            'messages' => collect($messages)->map(function (array $message) use ($selectedMessageId, $showRoute) {
                $id = (string) ($message['id'] ?? '');

                return [
                    'id' => $id,
                    'sender_name' => (string) ($message['sender_name'] ?? 'Unknown sender'),
                    'subject' => (string) ($message['subject'] ?? '(No subject)'),
                    'snippet' => (string) ($message['snippet'] ?? ''),
                    'unread' => (bool) ($message['unread'] ?? false),
                    'is_selected' => $selectedMessageId !== '' && $selectedMessageId === $id,
                    'received_at_display' => DateTimeFormat::formatDateTime($message['received_at'] ?? null, ''),
                    'routes' => [
                        'show' => route($showRoute, ['message' => $id]),
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
            'thread_messages' => collect($threadMessages)->map(function (array $threadMessage) {
                return [
                    'id' => (string) ($threadMessage['id'] ?? ''),
                    'sender_name' => (string) ($threadMessage['sender_name'] ?? 'Unknown sender'),
                    'sender_email' => (string) ($threadMessage['sender_email'] ?? ''),
                    'to' => (string) ($threadMessage['to'] ?? ''),
                    'subject' => (string) ($threadMessage['subject'] ?? '(No subject)'),
                    'body' => (string) ($threadMessage['body'] ?? ''),
                    'received_at_display' => DateTimeFormat::formatDateTime($threadMessage['received_at'] ?? null, ''),
                ];
            })->values()->all(),
            'sync_meta' => $syncMeta,
        ];
    }

    private function resolveMailboxData(
        Request $request,
        ApptimaticEmailStubRepository $stub,
        ?string $selectedMessageId = null
    ): array {
        $session = $this->mailSessionService->validateSession($request);
        $password = $this->mailSessionService->decryptPassword($request);
        $mailAccount = $session?->mailAccount;

        if (
            $mailAccount
            && is_string($password)
            && $password !== ''
            && $this->imapInboxService->isAvailable()
        ) {
            $messages = $this->imapInboxService->inbox($mailAccount, $password, 80);

            if ($messages !== []) {
                $selectedMessage = $selectedMessageId
                    ? collect($messages)->first(fn (array $message) => (string) ($message['id'] ?? '') === (string) $selectedMessageId)
                    : ($messages[0] ?? null);

                abort_if($selectedMessageId !== null && ! $selectedMessage, 404);

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

                return [$messages, $selectedMessage, $threadMessages, $this->syncMeta('live')];
            }
        }

        $messages = $stub->inbox();
        $selectedMessage = $selectedMessageId
            ? $stub->find($selectedMessageId)
            : ($messages[0] ?? null);
        abort_if($selectedMessageId !== null && ! $selectedMessage, 404);

        $threadMessages = $selectedMessage
            ? $stub->threadFor((string) ($selectedMessage['id'] ?? ''))
            : [];

        return [$messages, $selectedMessage, $threadMessages, $this->syncMeta('stub')];
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

        if (
            $mailAccount
            && is_string($password)
            && $password !== ''
            && $this->imapInboxService->isAvailable()
        ) {
            $hash = $this->imapInboxService->snapshotHash($mailAccount, $password, 40);
            if ($hash !== '') {
                return ['ok' => true, 'mode' => 'live', 'hash' => $hash];
            }
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
}
