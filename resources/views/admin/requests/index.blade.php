@extends('layouts.admin')

@section('title', 'Requests')
@section('page-title', 'Requests')

@section('content')
    <h1 class="mb-6 text-2xl font-semibold text-slate-900">Client Requests</h1>

    <div class="card overflow-hidden">
        <table class="w-full min-w-[980px] text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Type</th>
                    <th class="px-4 py-3">Customer</th>
                    <th class="px-4 py-3">Resource</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Submitted</th>
                    <th class="px-4 py-3">Message</th>
                    <th class="px-4 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $request)
                    @php
                        $typeLabel = ucwords(str_replace('_', ' ', $request->type));
                        $resource = '--';

                        if ($request->invoice) {
                            $resource = 'Invoice #' . $request->invoice->number;
                        } elseif ($request->subscription) {
                            $plan = $request->subscription->plan;
                            $product = $plan?->product;
                            $resource = ($product?->name ?? 'Service') . ' - ' . ($plan?->name ?? '--');
                        } elseif ($request->licenseDomain) {
                            $resource = $request->licenseDomain->domain;
                        }
                    @endphp
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $typeLabel }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $request->customer?->name ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $resource }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ ucfirst($request->status) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $request->created_at?->format('d-m-Y') ?? '--' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $request->message ?: '--' }}</td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('admin.requests.update', $request) }}" class="flex items-center justify-end gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="status" class="rounded-lg border border-slate-200 px-2 py-1 text-xs text-slate-600">
                                    <option value="pending" @selected($request->status === 'pending')>Pending</option>
                                    <option value="approved" @selected($request->status === 'approved')>Approved</option>
                                    <option value="rejected" @selected($request->status === 'rejected')>Rejected</option>
                                    <option value="completed" @selected($request->status === 'completed')>Completed</option>
                                </select>
                                <button type="submit" class="text-xs font-semibold text-teal-600 hover:text-teal-500">Update</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-500">No requests yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
