<?php

namespace App\Http\Controllers;

use App\Models\SupportTicketReply;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachmentController extends Controller
{
    public function show(SupportTicketReply $reply)
    {
        if (! $reply->attachment_path) {
            abort(404);
        }

        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($reply->attachment_path)) {
            abort(404);
        }

        // Admin and support roles can view all attachments.
        if ($user->isAdmin() || $user->isSupport()) {
            return $disk->response($reply->attachment_path);
        }

        // Client can only access attachments for their own ticket.
        if (! $user->isClient()) {
            abort(403);
        }

        $ticket = $reply->relationLoaded('ticket') ? $reply->ticket : $reply->ticket()->first();
        if (! $ticket || $ticket->customer_id !== $user->customer_id) {
            abort(403);
        }

        return $disk->response($reply->attachment_path);
    }
}
