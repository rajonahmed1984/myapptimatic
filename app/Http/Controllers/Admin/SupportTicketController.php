<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $tickets = $ticketsQuery->get();

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

    public function reply(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
        ]);

        $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_admin' => true,
        ]);

        $ticket->update([
            'status' => 'answered',
            'last_reply_at' => now(),
            'last_reply_by' => 'admin',
            'closed_at' => null,
        ]);

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'answered', 'customer_reply', 'closed'])],
        ]);

        $ticket->update([
            'status' => $data['status'],
            'closed_at' => $data['status'] === 'closed' ? now() : null,
        ]);

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

        $ticket->update([
            'subject' => $data['subject'],
            'priority' => $data['priority'],
            'status' => $data['status'],
            'closed_at' => $data['status'] === 'closed' ? now() : null,
        ]);

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Ticket updated.');
    }

    public function destroy(SupportTicket $ticket)
    {
        $ticket->delete();

        return redirect()
            ->route('admin.support-tickets.index')
            ->with('status', 'Ticket deleted.');
    }
}
