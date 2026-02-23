<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApptimaticEmailStubRepository;
use App\Support\HybridUiResponder;
use App\Support\UiFeature;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class ApptimaticEmailController extends Controller
{
    public function inbox(
        Request $request,
        ApptimaticEmailStubRepository $mailbox,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $messages = $mailbox->inbox();
        $selectedMessage = $messages[0] ?? null;
        $threadMessages = $selectedMessage
            ? $mailbox->threadFor((string) $selectedMessage['id'])
            : [];
        $payload = $this->viewData(
            $request,
            $mailbox,
            $messages,
            $selectedMessage,
            $threadMessages
        );

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_APPTIMATIC_EMAIL_INBOX,
            'admin.apptimatic-email.inbox',
            $payload,
            'Admin/ApptimaticEmail/Inbox',
            $this->inboxInertiaProps($payload)
        );
    }

    public function show(
        Request $request,
        string $message,
        ApptimaticEmailStubRepository $mailbox,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $messages = $mailbox->inbox();
        $selectedMessage = $mailbox->find($message);
        abort_if(! $selectedMessage, 404);

        $threadMessages = $mailbox->threadFor($message);
        $payload = $this->viewData(
            $request,
            $mailbox,
            $messages,
            $selectedMessage,
            $threadMessages
        );

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_APPTIMATIC_EMAIL_SHOW,
            'admin.apptimatic-email.show',
            $payload,
            'Admin/ApptimaticEmail/Inbox',
            $this->inboxInertiaProps($payload)
        );
    }

    private function viewData(
        Request $request,
        ApptimaticEmailStubRepository $mailbox,
        array $messages,
        ?array $selectedMessage,
        array $threadMessages
    ): array {
        return [
            'messages' => $messages,
            'selectedMessage' => $selectedMessage,
            'threadMessages' => $threadMessages,
            'unreadCount' => $mailbox->unreadCount(),
            'portalLabel' => 'Admin portal',
            'profileName' => $request->user()?->name ?? 'Administrator',
            'profileAvatarPath' => $request->user()?->avatar_path,
        ];
    }

    private function inboxInertiaProps(array $payload): array
    {
        $messages = is_array($payload['messages'] ?? null) ? $payload['messages'] : [];
        $selectedMessage = is_array($payload['selectedMessage'] ?? null) ? $payload['selectedMessage'] : null;
        $threadMessages = is_array($payload['threadMessages'] ?? null) ? $payload['threadMessages'] : [];
        $selectedMessageId = (string) ($selectedMessage['id'] ?? '');

        return [
            'pageTitle' => 'Apptimatic Email',
            'unread_count' => (int) ($payload['unreadCount'] ?? 0),
            'portal_label' => (string) ($payload['portalLabel'] ?? 'Admin portal'),
            'profile_name' => (string) ($payload['profileName'] ?? 'Administrator'),
            'profile_avatar_path' => $payload['profileAvatarPath'] ?? null,
            'routes' => [
                'inbox' => route('admin.apptimatic-email.inbox'),
            ],
            'messages' => collect($messages)->map(function (array $message) use ($selectedMessageId) {
                $id = (string) ($message['id'] ?? '');

                return [
                    'id' => $id,
                    'sender_name' => (string) ($message['sender_name'] ?? 'Unknown sender'),
                    'subject' => (string) ($message['subject'] ?? '(No subject)'),
                    'snippet' => (string) ($message['snippet'] ?? ''),
                    'unread' => (bool) ($message['unread'] ?? false),
                    'is_selected' => $selectedMessageId !== '' && $selectedMessageId === $id,
                    'received_at_display' => $this->formatDate($message['received_at'] ?? null, 'M d, H:i'),
                    'routes' => [
                        'show' => route('admin.apptimatic-email.show', ['message' => $id]),
                    ],
                ];
            })->values()->all(),
            'selected_message' => $selectedMessage ? [
                'id' => (string) ($selectedMessage['id'] ?? ''),
                'sender_name' => (string) ($selectedMessage['sender_name'] ?? 'Unknown sender'),
                'sender_email' => (string) ($selectedMessage['sender_email'] ?? ''),
                'to' => (string) ($selectedMessage['to'] ?? ''),
                'subject' => (string) ($selectedMessage['subject'] ?? '(No subject)'),
                'received_at_display' => $this->formatDate($selectedMessage['received_at'] ?? null, 'M d, Y H:i'),
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
                    'received_at_display' => $this->formatDate($threadMessage['received_at'] ?? null, 'M d, Y H:i'),
                ];
            })->values()->all(),
        ];
    }

    private function formatDate(mixed $value, string $format): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        return '';
    }
}
