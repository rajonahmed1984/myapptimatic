<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\AdminNotificationService;
use App\Services\ClientNotificationService;
use App\Support\AjaxResponse;
use App\Support\SystemLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class SupportTicketController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $customer = $request->user()?->customer;

        $tickets = $customer
            ? SupportTicket::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('updated_at')
                ->get()
            : collect();

        $dateFormat = config('app.date_format', 'd-m-Y');

        return Inertia::render('Client/SupportTickets/Index', [
            'tickets' => $tickets->map(function (SupportTicket $ticket) use ($dateFormat) {
                return [
                    'id' => $ticket->id,
                    'number' => 'TKT-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT),
                    'subject' => (string) $ticket->subject,
                    'status' => (string) $ticket->status,
                    'status_label' => ucfirst(str_replace('_', ' ', (string) $ticket->status)),
                    'status_classes' => match ($ticket->status) {
                        'open' => 'bg-amber-100 text-amber-700',
                        'answered' => 'bg-emerald-100 text-emerald-700',
                        'customer_reply' => 'bg-blue-100 text-blue-700',
                        'closed' => 'bg-slate-100 text-slate-600',
                        default => 'bg-slate-100 text-slate-600',
                    },
                    'last_reply_at_display' => $ticket->last_reply_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?: '--',
                    'routes' => [
                        'show' => route('client.support-tickets.show', $ticket),
                    ],
                ];
            })->values()->all(),
            'routes' => [
                'create' => route('client.support-tickets.create'),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $customer = $request->user()?->customer;

        if (! $customer) {
            abort(403);
        }

        return Inertia::render('Client/SupportTickets/Create', [
            'form' => [
                'subject' => old('subject'),
                'priority' => old('priority', 'medium'),
                'message' => old('message'),
            ],
            'routes' => [
                'index' => route('client.support-tickets.index'),
                'store' => route('client.support-tickets.store'),
            ],
        ]);
    }

    public function store(
        Request $request,
        AdminNotificationService $adminNotifications,
        ClientNotificationService $clientNotifications
    ): RedirectResponse|JsonResponse {
        $customer = $request->user()?->customer;

        if (! $customer) {
            abort(403);
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'message' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5120'],
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
            'attachment_path' => $this->storeReplyAttachment($request),
        ]);

        SystemLogger::write('activity', 'Ticket created.', [
            'ticket_id' => $ticket->id,
            'customer_id' => $customer->id,
            'priority' => $ticket->priority,
        ], $request->user()?->id, $request->ip());

        SystemLogger::write('ticket_mail_import', 'Client opened support ticket.', [
            'ticket_id' => $ticket->id,
            'ticket_number' => 'TKT-'.str_pad($ticket->id, 5, '0', STR_PAD_LEFT),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'subject' => $ticket->subject,
            'priority' => $ticket->priority,
            'message' => substr($data['message'], 0, 100),
        ]);

        $clientNotifications->sendTicketOpened($ticket);
        $adminNotifications->sendTicketCreated($ticket);

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Ticket created.', [], route('client.support-tickets.show', $ticket), false);
        }

        return redirect()
            ->route('client.support-tickets.show', $ticket)
            ->with('status', 'Ticket created.');
    }

    public function show(Request $request, SupportTicket $ticket): InertiaResponse
    {
        $this->ensureOwnership($request, $ticket);

        $ticket->load(['replies.user', 'customer']);

        $dateFormat = config('app.date_format', 'd-m-Y');

        return Inertia::render('Client/SupportTickets/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'number' => 'TKT-'.str_pad((string) $ticket->id, 5, '0', STR_PAD_LEFT),
                'subject' => (string) $ticket->subject,
                'priority' => (string) $ticket->priority,
                'priority_label' => ucfirst((string) $ticket->priority),
                'status' => (string) $ticket->status,
                'status_label' => ucfirst(str_replace('_', ' ', (string) $ticket->status)),
                'status_classes' => match ($ticket->status) {
                    'open' => 'bg-amber-100 text-amber-700',
                    'answered' => 'bg-emerald-100 text-emerald-700',
                    'customer_reply' => 'bg-blue-100 text-blue-700',
                    'closed' => 'bg-slate-100 text-slate-600',
                    default => 'bg-slate-100 text-slate-600',
                },
                'created_at_display' => $ticket->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?: '--',
                'is_closed' => $ticket->isClosed(),
            ],
            'replies' => $ticket->replies->map(function ($reply) use ($dateFormat) {
                return [
                    'id' => $reply->id,
                    'is_admin' => (bool) $reply->is_admin,
                    'user_name' => $reply->user?->name ?? ($reply->is_admin ? 'Support' : 'You'),
                    'created_at_display' => $reply->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?: '--',
                    'message' => (string) $reply->message,
                    'attachment_url' => $reply->attachmentUrl(),
                    'attachment_name' => $reply->attachmentName(),
                ];
            })->values()->all(),
            'form' => [
                'message' => old('message'),
            ],
            'routes' => [
                'index' => route('client.support-tickets.index'),
                'reply' => route('client.support-tickets.reply', $ticket),
                'status' => route('client.support-tickets.status', $ticket),
            ],
        ]);
    }

    public function reply(
        Request $request,
        SupportTicket $ticket,
        AdminNotificationService $adminNotifications
    ): RedirectResponse|JsonResponse {
        $this->ensureOwnership($request, $ticket);

        $data = $request->validate([
            'message' => ['required', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,pdf', 'max:5120'],
        ]);

        $reply = $ticket->replies()->create([
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_admin' => false,
            'attachment_path' => $this->storeReplyAttachment($request),
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
            'ticket_number' => 'TKT-'.str_pad($ticket->id, 5, '0', STR_PAD_LEFT),
            'customer_id' => $ticket->customer_id,
            'customer_name' => $ticket->customer->name,
            'subject' => $ticket->subject,
            'message' => substr($data['message'], 0, 100),
        ]);

        $adminNotifications->sendTicketReplyFromClient($ticket, $reply);

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Reply sent.', $this->mainPatches($ticket), closeModal: false);
        }

        return redirect()
            ->route('client.support-tickets.show', $ticket)
            ->with('status', 'Reply sent.');
    }

    public function updateStatus(Request $request, SupportTicket $ticket): RedirectResponse|JsonResponse
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

        if (AjaxResponse::ajaxFromRequest($request)) {
            return AjaxResponse::ajaxOk('Ticket updated.', $this->mainPatches($ticket), closeModal: false);
        }

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

    private function storeReplyAttachment(Request $request): ?string
    {
        if (! $request->hasFile('attachment')) {
            return null;
        }

        $file = $request->file('attachment');
        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        if ($name === '') {
            $name = 'attachment';
        }
        $fileName = $name.'-'.time().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('support-ticket-replies', $fileName, 'public');
    }

    private function mainPatches(SupportTicket $ticket): array
    {
        $ticket->refresh()->load(['replies.user', 'customer']);

        return [
            [
                'action' => 'replace',
                'selector' => '#ticketMainWrap',
                'html' => view('client.support-tickets.partials.main', [
                    'ticket' => $ticket,
                ])->render(),
            ],
        ];
    }
}
