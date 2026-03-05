<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Services\ApptimaticEmailStubRepository;
use App\Services\Mail\MailSessionService;
use App\Support\DateTimeFormat;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MailInboxController extends Controller
{
    public function __construct(private readonly MailSessionService $mailSessionService)
    {
    }

    public function index(Request $request, ApptimaticEmailStubRepository $mailbox): InertiaResponse
    {
        $messages = $mailbox->inbox();
        $selectedMessage = $messages[0] ?? null;
        $threadMessages = $selectedMessage ? $mailbox->threadFor((string) $selectedMessage['id']) : [];

        return Inertia::render('Admin/ApptimaticEmail/Inbox', $this->inboxInertiaProps($request, $mailbox, $messages, $selectedMessage, $threadMessages));
    }

    public function show(Request $request, string $message, ApptimaticEmailStubRepository $mailbox): InertiaResponse
    {
        $messages = $mailbox->inbox();
        $selectedMessage = $mailbox->find($message);
        abort_if(! $selectedMessage, 404);
        $threadMessages = $mailbox->threadFor($message);

        return Inertia::render('Admin/ApptimaticEmail/Inbox', $this->inboxInertiaProps($request, $mailbox, $messages, $selectedMessage, $threadMessages));
    }

    private function inboxInertiaProps(
        Request $request,
        ApptimaticEmailStubRepository $mailbox,
        array $messages,
        ?array $selectedMessage,
        array $threadMessages
    ): array {
        $routeName = (string) $request->route()?->getName();
        $inboxRoute = $this->resolveRoute($routeName, 'inbox');
        $showRoute = $this->resolveRoute($routeName, 'show');
        $logoutRoute = $this->resolveRoute($routeName, 'logout');
        $loginRoute = $this->resolveRoute($routeName, 'login');

        $selectedMessageId = (string) ($selectedMessage['id'] ?? '');

        return [
            'pageTitle' => 'Apptimatic Email',
            'unread_count' => $mailbox->unreadCount(),
            'portal_label' => $this->portalLabelFromRoute($routeName),
            'profile_name' => (string) ($request->user()?->name ?? 'User'),
            'profile_avatar_path' => $request->user()?->avatar_path,
            'routes' => [
                'inbox' => route($inboxRoute),
                'logout' => route($logoutRoute),
                'login' => route($loginRoute),
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
        ];
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
