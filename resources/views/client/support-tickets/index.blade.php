@extends('layouts.client')

@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Support Tickets</h1>
            <p class="mt-1 text-sm text-slate-500">Open a ticket or reply to existing requests.</p>
        </div>
        <a href="{{ route('client.support-tickets.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Ticket</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Ticket</th>
                    <th class="px-4 py-3">Subject</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Last Reply</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                    @php
                        $label = ucfirst(str_replace('_', ' ', $ticket->status));
                        $statusClasses = match ($ticket->status) {
                            'open' => 'bg-amber-100 text-amber-700',
                            'answered' => 'bg-emerald-100 text-emerald-700',
                            'customer_reply' => 'bg-blue-100 text-blue-700',
                            'closed' => 'bg-slate-100 text-slate-600',
                            default => 'bg-slate-100 text-slate-600',
                        };
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">TKT-{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">{{ $label }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ $ticket->last_reply_at?->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('client.support-tickets.show', $ticket) }}" class="text-teal-600 hover:text-teal-500">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-slate-500">No tickets yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
