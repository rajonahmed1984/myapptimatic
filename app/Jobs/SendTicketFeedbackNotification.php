<?php

namespace App\Jobs;

use App\Models\SupportTicket;
use App\Services\ClientNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTicketFeedbackNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ticketId)
    {
    }

    public function handle(ClientNotificationService $clientNotifications): void
    {
        $ticket = SupportTicket::find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $clientNotifications->sendTicketFeedback($ticket);
    }
}
