<?php

namespace App\Http\Controllers;

use App\Models\SupportTicketReply;
use Illuminate\Support\Facades\Storage;

class SupportTicketAttachmentController extends Controller
{
    public function show(SupportTicketReply $reply)
    {
        if (! $reply->attachment_path) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($reply->attachment_path)) {
            abort(404);
        }

        return $disk->response($reply->attachment_path);
    }
}
