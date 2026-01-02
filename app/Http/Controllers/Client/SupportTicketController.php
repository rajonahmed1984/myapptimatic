<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\AdminNotificationService;
use App\Services\ClientNotificationService;
use App\Support\SystemLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user()?->customer;

        $tickets = $customer
            ? SupportTicket::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('updated_at')
                ->get()
            : collect();

        return view('client.support-tickets.index', [
            'customer' => $customer,
            'tickets' => $tickets,
        ]);
    }

    public function create(Request $request)
    {
        $customer = $request->user()?->customer;

        if (! $customer) {
            abort(403);
        }

        return view('client.support-tickets.create', [
            'customer' => $customer,
        ]);
    }

    public function store(
        Request $request,
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications
    ) {
        $customer = $request->user()?->customer;

        if (! $customer) {
            abort(403);
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'message' => ['required', 'string'],
        ]);

        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'priority' => $data['priority'],
            'status' => 'open',
            'last_reply_at' => now(),
            'last_reply_by' => 'client',
        ]);

        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_admin' => false,
        ]);

        SystemLogger::write('activity', 'Ticket created.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $customer->id,
            'priority' => $ticket->priority,
        ], $request->user()?->id, $request->ip());

        SystemLogger::write('ticket_mail_import', 'Client opened support ticket.', [
            'ticket_id' => $ticket->id,
            'ticket_number' => 'TKT-' . str_pad($ticket->id, 5, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'subject' => $ticket->subject,
            'priority' => $ticket->priority,
            'message' => substr($data['message'], 0, 100),
        ]);

        $clientNotifications->sendTicketOpened($ticket);
        $adminNotifications->sendTicketCreated($ticket);

        return redirect()
            ->route('client.support-tickets.show', $ticket)
            ->with('status', 'Ticket created.');
    }

    public function show(Request $request, SupportTicket $ticket)
    {
        $this->ensureOwnership($request, $ticket);

        $ticket->load(['replies.user', 'customer']);

        return view('client.support-tickets.show', [
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket)
    {
        $this->ensureOwnership($request, $ticket);

        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_admin' => false,
        ]);

        $ticket->update([
            'status' => 'customer_reply',
            'last_reply_at' => now(),
            'last_reply_by' => 'client',
            'closed_at' => null,
        ]);

        SystemLogger::write('activity', 'Ticket replied by client.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'status' => $ticket->status,
        ], $request->user()?->id, $request->ip());

        SystemLogger::write('ticket_mail_import', 'Client replied to support ticket.', [
            'ticket_id' => $ticket->id,
            'ticket_number' => 'TKT-' . str_pad($ticket->id, 5, '0', STR_PAD_LEFT),
            'customer_id' => $ticket->customer_id,
            'customer_name' => $ticket->customer->name,
            'subject' => $ticket->subject,
            'message' => substr($data['message'], 0, 100),
        ]);

        return redirect()
            ->route('client.support-tickets.show', $ticket)
            ->with('status', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $this->ensureOwnership($request, $ticket);

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);

        $ticket->update([
            'status' => $data['status'],
            'closed_at' => $data['status'] === 'closed' ? now() : null,
        ]);

        SystemLogger::write('activity', 'Ticket status updated by client.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'status' => $ticket->status,
        ], $request->user()?->id, $request->ip());

        return redirect()
            ->route('client.support-tickets.show', $ticket)
            ->with('status', 'Ticket updated.');
    }

    private function ensureOwnership(Request $request, SupportTicket $ticket): void
    {
        $customer = $request->user()?->customer;

        if (! $customer || $ticket->customer_id !== $customer->id) {
            abort(404);
        }
    }
}
