@extends('layouts.admin')

@section('title', 'Support Tickets')
@section('page-title', 'Support Tickets')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Support Tickets</h1>
            <p class="mt-1 text-sm text-slate-500">Track and reply to client support requests.</p>
        </div>
    </div>

    <div class="card p-4">
        <div class="flex flex-wrap gap-2 text-xs">
            @php
                $filters = [
                    'all' => ['label' => 'All', 'count' => $statusCounts['all'] ?? 0],
                    'open' => ['label' => 'Open', 'count' => $statusCounts['open'] ?? 0],
                    'answered' => ['label' => 'Answered', 'count' => $statusCounts['answered'] ?? 0],
                    'customer_reply' => ['label' => 'Customer Reply', 'count' => $statusCounts['customer_reply'] ?? 0],
                    'closed' => ['label' => 'Closed', 'count' => $statusCounts['closed'] ?? 0],
                ];
            @endphp

            @foreach($filters as $key => $filter)
                @php
                    $active = ($status === $key) || ($key === 'all' && empty($status));
                    $url = $key === 'all' ? route('admin.support-tickets.index') : route('admin.support-tickets.index', ['status' => $key]);
                @endphp
                <a href="{{ $url }}" class="rounded-full border px-3 py-1 {{ $active ? 'border-teal-200 bg-teal-50 text-teal-600' : 'border-slate-200 text-slate-500 hover:text-teal-600' }}">
                    {{ $filter['label'] }} ({{ $filter['count'] }})
                </a>
            @endforeach
        </div>
    </div>

    <div class="card mt-6 overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">SL</th>
                    <th class="px-4 py-3">Ticket</th>
                    <th class="px-4 py-3">Subject</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Last Reply</th>
                    <th class="px-4 py-3">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $ticket)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 text-slate-500">{{ $tickets->firstItem() ? $tickets->firstItem() + $loop->index : $ticket->id }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">TKT-{{ str_pad($ticket->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $ticket->customer->name }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$ticket->status" :label="ucfirst(str_replace('_', ' ', $ticket->status))" />
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ $ticket->last_reply_at?->format($globalDateFormat . ' H:i') ?? '--' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.support-tickets.show', $ticket) }}#replies" class="text-teal-600 hover:text-teal-500">Reply</a>
                                <a href="{{ route('admin.support-tickets.show', $ticket) }}" class="text-slate-600 hover:text-slate-500">View</a>
                                <form method="POST" action="{{ route('admin.support-tickets.destroy', $ticket) }}" onsubmit="return confirm('Delete this ticket and all replies?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No support tickets yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $tickets->links() }}
    </div>
@endsection
