<?php

namespace App\Jobs;

use App\Models\SupportTicket;
use App\Services\AdminNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTicketAdminReminderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $ticketId)
    {
    }

    public function handle(AdminNotificationService $adminNotifications): void
    {
        $ticket = SupportTicket::find($this->ticketId);

        if (! $ticket) {
            return;
        }

        $adminNotifications->sendTicketReminder($ticket);
    }
}
