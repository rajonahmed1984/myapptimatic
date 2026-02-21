<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApptimaticEmailStubRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApptimaticEmailController extends Controller
{
    public function inbox(Request $request, ApptimaticEmailStubRepository $mailbox): View
    {
        $messages = $mailbox->inbox();
        $selectedMessage = $messages[0] ?? null;
        $threadMessages = $selectedMessage
            ? $mailbox->threadFor((string) $selectedMessage['id'])
            : [];

        return view('admin.apptimatic-email.inbox', $this->viewData(
            $request,
            $mailbox,
            $messages,
            $selectedMessage,
            $threadMessages
        ));
    }

    public function show(
        Request $request,
        string $message,
        ApptimaticEmailStubRepository $mailbox
    ): View
    {
        $messages = $mailbox->inbox();
        $selectedMessage = $mailbox->find($message);
        abort_if(! $selectedMessage, 404);

        $threadMessages = $mailbox->threadFor($message);

        return view('admin.apptimatic-email.show', $this->viewData(
            $request,
            $mailbox,
            $messages,
            $selectedMessage,
            $threadMessages
        ));
    }

    private function viewData(
        Request $request,
        ApptimaticEmailStubRepository $mailbox,
        array $messages,
        ?array $selectedMessage,
        array $threadMessages
    ): array
    {
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
}
