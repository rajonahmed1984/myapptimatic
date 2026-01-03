<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\ClientNotificationService;
use App\Support\SystemLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class SupportTicketController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $allowedStatuses = ['open', 'answered', 'customer_reply', 'closed'];

        $ticketsQuery = SupportTicket::query()
            ->with(['customer'])
            ->orderByDesc('last_reply_at')
            ->orderByDesc('created_at');

        if ($status && in_array($status, $allowedStatuses, true)) {
            $ticketsQuery->where('status', $status);
        }

        $tickets = $ticketsQuery->paginate(25);

        $statusCounts = [
            'all' => SupportTicket::count(),
            'open' => SupportTicket::where('status', 'open')->count(),
            'answered' => SupportTicket::where('status', 'answered')->count(),
            'customer_reply' => SupportTicket::where('status', 'customer_reply')->count(),
            'closed' => SupportTicket::where('status', 'closed')->count(),
        ];

        return view('admin.support-tickets.index', [
            'tickets' => $tickets,
            'status' => $status,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function show(SupportTicket $ticket)
    {
        $ticket->load(['customer', 'replies.user']);

        return view('admin.support-tickets.show', [
            'ticket' => $ticket,
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, ClientNotificationService $clientNotifications)
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5120'],
        ]);

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $name = $name === '' ? 'attachment' : $name;
            $fileName = $name.'-'.time().'.'.$file->getClientOriginalExtension();
            $attachmentPath = $file->storeAs('support-ticket-replies', $fileName, 'public');
        }

        $reply = $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_admin' => true,
            'attachment_path' => $attachmentPath,
        ]);

        $ticket->update([
            'status' => 'answered',
            'last_reply_at' => now(),
            'last_reply_by' => 'admin',
            'closed_at' => null,
        ]);

        SystemLogger::write('activity', 'Ticket replied.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'status' => $ticket->status,
        ], $request->user()?->id, $request->ip());

        SystemLogger::write('ticket_mail_import', 'Admin replied to support ticket.', [
            'ticket_id' => $ticket->id,
            'ticket_number' => 'TKT-' . str_pad($ticket->id, 5, '0', STR_PAD_LEFT),
            'customer_id' => $ticket->customer_id,
            'customer_name' => $ticket->customer->name,
            'admin_name' => $request->user()->name,
            'subject' => $ticket->subject,
            'message' => substr($data['message'], 0, 100),
        ]);

        $clientNotifications->sendTicketReplyFromAdmin($ticket, $reply);

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'answered', 'customer_reply', 'closed'])],
        ]);

        $previousStatus = $ticket->status;
        $ticket->update([
            'status' => $data['status'],
            'closed_at' => $data['status'] === 'closed' ? now() : null,
        ]);

        SystemLogger::write('activity', 'Ticket status updated.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'from_status' => $previousStatus,
            'to_status' => $data['status'],
        ], $request->user()?->id, $request->ip());

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Ticket updated.');
    }

    public function update(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'status' => ['required', Rule::in(['open', 'answered', 'customer_reply', 'closed'])],
        ]);

        $previousStatus = $ticket->status;
        $ticket->update([
            'subject' => $data['subject'],
            'priority' => $data['priority'],
            'status' => $data['status'],
            'closed_at' => $data['status'] === 'closed' ? now() : null,
        ]);

        SystemLogger::write('activity', 'Ticket updated.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'from_status' => $previousStatus,
            'to_status' => $data['status'],
            'priority' => $data['priority'],
        ], $request->user()?->id, $request->ip());

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Ticket updated.');
    }

    public function destroy(SupportTicket $ticket)
    {
        SystemLogger::write('activity', 'Ticket deleted.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'status' => $ticket->status,
        ]);

        $ticket->delete();

        return redirect()
            ->route('admin.support-tickets.index')
            ->with('status', 'Ticket deleted.');
    }
}
