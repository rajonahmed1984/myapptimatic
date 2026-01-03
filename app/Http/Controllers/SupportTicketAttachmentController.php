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

        // Admins can view all attachments. Clients must own the ticket.
        if (! $user?->isAdmin()) {
            $customerId = $user?->customer_id;
            $ticket = $reply->relationLoaded('ticket') ? $reply->ticket : $reply->ticket()->first();

            if (! $ticket || $ticket->customer_id !== $customerId) {
                abort(403);
            }
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($reply->attachment_path)) {
            abort(404);
        }

        return $disk->response($reply->attachment_path);
    }
}
