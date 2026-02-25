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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request): InertiaResponse
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

        return Inertia::render(
            'Admin/SupportTickets/Index',
            $this->indexInertiaProps($tickets, $status, $statusCounts)
        );
    }

    public function create(Request $request): InertiaResponse
    {
        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'company_name', 'email']);

        return Inertia::render(
            'Admin/SupportTickets/Create',
            $this->createInertiaProps($customers, $request->query('customer_id'))
        );
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
            'ticket_number' => 'TKT-'.str_pad($ticket->id, 5, '0', STR_PAD_LEFT),
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

    public function show(SupportTicket $ticket): InertiaResponse
    {
        $ticket->load(['customer', 'replies.user']);

        return Inertia::render(
            'Admin/SupportTickets/Show',
            $this->showInertiaProps($ticket)
        );
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
            'ticket_number' => 'TKT-'.str_pad($ticket->id, 5, '0', STR_PAD_LEFT),
            'customer_id' => $ticket->customer_id,
            'customer_name' => $ticket->customer->name,
            'admin_name' => $request->user()->name,
            'subject' => $ticket->subject,
            'message' => substr($data['message'], 0, 100),
        ]);

        $clientNotifications->sendTicketReplyFromAdmin($ticket, $reply);

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxRedirect(
                route('admin.support-tickets.show', $ticket),
                'Reply sent.'
            );
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
            return AjaxResponse::ajaxRedirect(
                route('admin.support-tickets.show', $ticket),
                'Ticket updated.'
            );
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
            return AjaxResponse::ajaxRedirect(
                route('admin.support-tickets.show', $ticket),
                'Ticket updated.'
            );
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

    private function indexInertiaProps(
        LengthAwarePaginator $tickets,
        ?string $status,
        array $statusCounts
    ): array {
        $dateFormat = config('app.date_format', 'd-m-Y');
        $activeStatus = $status ?: 'all';
        $filters = [
            'all' => 'All',
            'open' => 'Open',
            'answered' => 'Answered',
            'customer_reply' => 'Customer Reply',
            'closed' => 'Closed',
        ];

        return [
            'pageTitle' => 'Support Tickets',
            'active_status' => $activeStatus,
            'filter_links' => collect($filters)->map(function (string $label, string $key) use ($activeStatus, $statusCounts) {
                return [
                    'key' => $key,
                    'label' => $label,
                    'count' => (int) ($statusCounts[$key] ?? 0),
                    'active' => $activeStatus === $key,
                    'href' => $key === 'all'
                        ? route('admin.support-tickets.index')
                        : route('admin.support-tickets.index', ['status' => $key]),
                ];
            })->values()->all(),
            'routes' => [
                'create' => route('admin.support-tickets.create'),
            ],
            'tickets' => collect($tickets->items())->values()->map(function (SupportTicket $ticket, int $index) use ($tickets, $dateFormat) {
                return [
                    'id' => $ticket->id,
                    'serial' => $tickets->firstItem() ? $tickets->firstItem() + $index : $ticket->id,
                    'ticket_number' => 'TKT-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT),
                    'subject' => (string) $ticket->subject,
                    'customer_name' => (string) ($ticket->customer->name ?? '--'),
                    'status' => (string) $ticket->status,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $ticket->status)),
                    'last_reply_at_display' => $ticket->last_reply_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                    'routes' => [
                        'show' => route('admin.support-tickets.show', $ticket),
                        'reply' => route('admin.support-tickets.show', $ticket).'#replies',
                        'destroy' => route('admin.support-tickets.destroy', $ticket),
                    ],
                ];
            })->all(),
            'pagination' => [
                'has_pages' => $tickets->hasPages(),
                'previous_url' => $tickets->previousPageUrl(),
                'next_url' => $tickets->nextPageUrl(),
            ],
        ];
    }

    private function createInertiaProps($customers, mixed $selectedCustomerId): array
    {
        return [
            'pageTitle' => 'Create Support Ticket',
            'selected_customer_id' => $selectedCustomerId !== null ? (string) $selectedCustomerId : '',
            'customers' => collect($customers)->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => (string) $customer->name,
                'email' => (string) ($customer->email ?? ''),
                'company_name' => (string) ($customer->company_name ?? ''),
            ])->values()->all(),
            'routes' => [
                'index' => route('admin.support-tickets.index'),
                'store' => route('admin.support-tickets.store'),
            ],
        ];
    }

    private function showInertiaProps(SupportTicket $ticket): array
    {
        $dateFormat = config('app.date_format', 'd-m-Y');

        return [
            'pageTitle' => 'Support Ticket',
            'ai_ready' => (bool) config('google_ai.api_key'),
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => 'TKT-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT),
                'subject' => (string) $ticket->subject,
                'priority' => (string) $ticket->priority,
                'status' => (string) $ticket->status,
                'status_label' => ucfirst(str_replace('_', ' ', (string) $ticket->status)),
                'customer_name' => (string) ($ticket->customer?->name ?? '--'),
                'created_at_display' => $ticket->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                'last_reply_at_display' => $ticket->last_reply_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
            ],
            'replies' => $ticket->replies->map(function ($reply) use ($dateFormat) {
                return [
                    'id' => $reply->id,
                    'message' => (string) $reply->message,
                    'is_admin' => (bool) $reply->is_admin,
                    'author_name' => (string) ($reply->user?->name ?? 'System'),
                    'created_at_display' => $reply->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                    'attachment_url' => $reply->attachment_path
                        ? route('support-ticket-replies.attachment', $reply)
                        : null,
                ];
            })->values()->all(),
            'routes' => [
                'index' => route('admin.support-tickets.index'),
                'reply' => route('admin.support-tickets.reply', $ticket),
                'update' => route('admin.support-tickets.update', $ticket),
                'status' => route('admin.support-tickets.status', $ticket),
                'destroy' => route('admin.support-tickets.destroy', $ticket),
                'ai_summary' => route('admin.support-tickets.ai', $ticket),
            ],
        ];
    }
}
