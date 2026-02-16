<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\SupportTicket;
use App\Services\ClientNotificationService;
use App\Services\GeminiService;
use App\Services\SupportTicketAiService;
use App\Support\AjaxResponse;
use App\Support\SystemLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
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

    public function create(Request $request)
    {
        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'company_name', 'email']);

        return view('admin.support-tickets.create', [
            'customers' => $customers,
            'selectedCustomerId' => $request->query('customer_id'),
        ]);
    }

    public function store(Request $request, ClientNotificationService $clientNotifications)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'message' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5120'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);

        $ticket = SupportTicket::create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'priority' => $data['priority'],
            'status' => 'answered',
            'last_reply_at' => now(),
            'last_reply_by' => 'admin',
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

        SystemLogger::write('activity', 'Ticket created by admin.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $customer->id,
            'priority' => $ticket->priority,
        ], $request->user()?->id, $request->ip());

        SystemLogger::write('ticket_mail_import', 'Admin opened support ticket.', [
            'ticket_id' => $ticket->id,
            'ticket_number' => 'TKT-' . str_pad($ticket->id, 5, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'admin_name' => $request->user()->name,
            'subject' => $ticket->subject,
            'message' => substr($data['message'], 0, 100),
        ]);

        $clientNotifications->sendTicketReplyFromAdmin($ticket, $reply);

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Ticket created.');
    }

    public function show(SupportTicket $ticket): View
    {
        $ticket->load(['customer', 'replies.user']);

        return view('admin.support-tickets.show', [
            'ticket' => $ticket,
            'aiReady' => (bool) config('google_ai.api_key'),
        ]);
    }

    public function aiSummary(
        Request $request,
        SupportTicket $ticket,
        SupportTicketAiService $aiService,
        GeminiService $geminiService
    ): JsonResponse {
        if (! config('google_ai.api_key')) {
            return response()->json(['error' => 'Missing GOOGLE_AI_API_KEY.'], 422);
        }

        $ticket->load(['customer', 'replies.user']);

        try {
            $result = $aiService->analyze($ticket, $geminiService);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function reply(Request $request, SupportTicket $ticket, ClientNotificationService $clientNotifications): RedirectResponse|JsonResponse
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

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Reply sent.', $this->mainPatches($ticket), closeModal: false);
        }

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse|JsonResponse
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

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Ticket updated.', $this->mainPatches($ticket), closeModal: false);
        }

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Ticket updated.');
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse|JsonResponse
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

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Ticket updated.', $this->mainPatches($ticket), closeModal: false);
        }

        return redirect()
            ->route('admin.support-tickets.show', $ticket)
            ->with('status', 'Ticket updated.');
    }

    public function destroy(Request $request, SupportTicket $ticket): RedirectResponse|JsonResponse
    {
        SystemLogger::write('activity', 'Ticket deleted.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $ticket->customer_id,
            'status' => $ticket->status,
        ]);

        $ticket->delete();

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Ticket deleted.', [], route('admin.support-tickets.index'), false);
        }

        return redirect()
            ->route('admin.support-tickets.index')
            ->with('status', 'Ticket deleted.');
    }

    private function mainPatches(SupportTicket $ticket): array
    {
        $ticket->refresh()->load(['customer', 'replies.user']);

        return [
            [
                'action' => 'replace',
                'selector' => '#ticketMainWrap',
                'html' => view('admin.support-tickets.partials.main', [
                    'ticket' => $ticket,
                ])->render(),
            ],
        ];
    }
}
